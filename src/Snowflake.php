<?php


namespace MuCTS\Laravel\EloquentMulti;

use Exception;

/**
 * Snowflake
 *
 * SnowFlake的结构如下(每部分用-分开):
 * 0 - 0000000000 0000000000 0000000000 0000000000 0 - 00000 - 00000 - 000000000000
 * 1位标识，由于long基本类型在Java中是带符号的，最高位是符号位，正数是0，负数是1，所以id一般是正数，最高位是0
 * 41位时间截(毫秒级)，注意，41位时间截不是存储当前时间的时间截，而是存储时间截的差值（当前时间截 - 开始时间截)
 * 得到的值），这里的的开始时间截，一般是我们的id生成器开始使用的时间，由我们程序来指定的（如下下面程序IdWorker类的startTime属性）。41位的时间截，可以使用69年，年T = (1L << 41) / (1000L * 60 * 60 * 24 * 365) = 69
 * 10位的数据机器位，可以部署在1024个节点，包括5位datacenterId和5位workerId
 * 12位序列，毫秒内的计数，12位的计数顺序号支持每个节点每毫秒(同一机器，同一时间截)产生4096个ID序号
 * 加起来刚好64位，为一个Long型。
 * SnowFlake的优点是，整体上按照时间自增排序，并且整个分布式系统内不会产生ID碰撞(由数据中心ID和机器ID作区分)，并且效率较高，经测试，SnowFlake每秒能够产生26万ID左右。
 */
class Snowflake
{
    /** 开始时间截 (2020-01-01) */
    private const TWEPOCH = 1577808000000;

    /** 机器id所占的位数 */
    private const WORKER_ID_BITS = 5;

    /** 数据标识id所占的位数 */
    private const DATA_CENTER_ID_BITS = 5;

    /** 支持的最大机器id，结果是31 (这个移位算法可以很快的计算出几位二进制数所能表示的最大十进制数) */
    private const MAX_WORKER_ID = -1 ^ (-1 << self::WORKER_ID_BITS);

    /** 支持的最大数据标识id，结果是31 */
    private const MAX_DATA_CENTER_ID = -1 ^ (-1 << self::DATA_CENTER_ID_BITS);


    /** 序列在id中占的位数 */
    private const SEQUENCE_BITS = 12;

    /** 机器ID向左移12位 */
    private const WORKER_ID_SHIFT = self::SEQUENCE_BITS;

    /** 数据标识id向左移17位(12+5) */
    private const DATA_CENTER_ID_SHIFT = self::SEQUENCE_BITS + self::WORKER_ID_BITS;

    /** 时间截向左移22位(5+5+12) */
    private const TIMESTAMP_LEFT_SHIFT = self::SEQUENCE_BITS + self::WORKER_ID_BITS + self::DATA_CENTER_ID_BITS;

    /** 生成序列的掩码，这里为4095 (0b111111111111=0xfff=4095) */
    private const SEQUENCE_MASK = -1 ^ (-1 << self::SEQUENCE_BITS);

    /** 工作机器ID(0~31) */
    private $workerId;

    /** 数据中心ID(0~31) */
    private $dataCenterId;

    /** 毫秒内序列(0~4095) */
    private static $sequence = 0;

    /** 上次生成ID的时间截 */
    private $lastTimestamp = -1;

    /**
     * 构造函数
     * @param int $workerId 工作ID (0~31)
     * @param int $dataCenterId 数据中心ID (0~31)
     * @throws Exception
     */
    public function __construct($workerId, $dataCenterId)
    {
        if ($workerId > self::MAX_WORKER_ID || $workerId < 0) {
            throw new Exception(sprintf('worker Id can\'t be greater than %d or less than 0', self::MAX_WORKER_ID));
        } elseif ($dataCenterId > self::MAX_DATA_CENTER_ID || $dataCenterId < 0) {
            throw new Exception(sprintf('data center Id can\'t be greater than %d or less than 0', self::MAX_DATA_CENTER_ID));
        }
        $this->workerId = $workerId;
        $this->dataCenterId = $dataCenterId;
    }

    /**
     * 获得下一个ID (该方法是线程安全的)
     * @return int
     * @throws Exception
     */
    public function nextId()
    {
        $timestamp = $this->timeGen();

        //如果当前时间小于上一次ID生成的时间戳，说明系统时钟回退过这个时候应当抛出异常
        if ($timestamp < $this->lastTimestamp) {
            throw new Exception(
                sprintf("Clock moved backwards.  Refusing to generate id for %d milliseconds", $this->lastTimestamp - $timestamp));
        }

        //如果是同一时间生成的，则进行毫秒内序列
        if ($this->lastTimestamp == $timestamp) {
            self::$sequence = (self::$sequence + 1) & self::SEQUENCE_MASK;
            //毫秒内序列溢出
            if (self::$sequence == 0) {
                //阻塞到下一个毫秒,获得新的时间戳
                $timestamp = $this->tilNextMillis($this->lastTimestamp);
            }
        } //时间戳改变，毫秒内序列重置
        else {
            self::$sequence = 0;
        }

        //上次生成ID的时间截
        $this->lastTimestamp = $timestamp;

        $gmpTimestamp = gmp_init($this->leftShift(bcsub($timestamp, self::TWEPOCH), self::TIMESTAMP_LEFT_SHIFT));
        $gmpDataCenterId = gmp_init($this->leftShift($this->dataCenterId, self::DATA_CENTER_ID_SHIFT));
        $gmpWorkerId = gmp_init($this->leftShift($this->workerId, self::WORKER_ID_SHIFT));
        $gmpSequence = gmp_init(self::$sequence);

        return gmp_strval(gmp_or(gmp_or(gmp_or($gmpTimestamp, $gmpDataCenterId), $gmpWorkerId), $gmpSequence));
    }

    /**
     * 阻塞到下一个毫秒，直到获得新的时间戳
     * @param int $lastTimestamp 上次生成ID的时间截
     * @return int 当前时间戳
     */
    protected function tilNextMillis($lastTimestamp)
    {
        $timestamp = $this->timeGen();
        while ($timestamp <= $lastTimestamp) {
            $timestamp = $this->timeGen();
        }
        return $timestamp;
    }

    /**
     * 返回以毫秒为单位的当前时间
     * @return int 当前时间(毫秒)
     */
    protected function timeGen()
    {
        return microtime(true) * 1000;
    }

    protected function leftShift($a, $b)
    {
        return bcmul($a, bcpow(2, $b));
    }
}