<?php


namespace MuCTS\Laravel\EloquentMulti\Models;


use Illuminate\Database\Query\Builder;

trait Multi
{
    /** @var bool is multi table */
    protected $isMulti = false;
    /** @var null|string multi table name */
    protected $multiTable = null;

    /**
     * @param string|int|null $value
     * @return Builder
     */
    public static function multiQuery($value = null)
    {
        return (new static())->setMultiTable($value)->newQuery();
    }

    /**
     * @param null $value
     * @return $this
     */
    public function setMultiTable($value = null)
    {
        $code = is_null($value) ?? (is_int($value) ? $value : hashcode($value));
        $tables = static::getConnectionName() ?? 'default';
        $tables = config('database.connections.' . $tables . '.tables');
        $this->multiTable = preg_replace('/_\d+$/', '', parent::getTable()) . '_' . (1 + $code % $tables);
        return $this;
    }

    /**
     * @return string|null
     */
    public function getMultiTable()
    {
        return $this->multiTable ?: static::setMultiTable()->multiTable;
    }
}