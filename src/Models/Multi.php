<?php


namespace MuCTS\LaravelEloquentMulti\Models;


trait Multi
{
    /** @var bool is multi table */
    protected $isMulti = false;
    /** @var null|string multi table name */
    protected $multiTable = null;

    public static function multiQuery($multiStr = null)
    {
        return (new static())->setMultiTable($multiStr)->newQuery();
    }

    public function setMultiTable($str = null)
    {
        $code = is_null($str) ?? (is_int($str) ? $str : hashcode($str));
        $tables = static::getConnectionName() ?? 'default';
        $tables = config('database.connections.' . $tables . '.tables');
        $this->multiTable = parent::getTable() . '_' . (1 + $code % $tables);
        return $this;
    }

    public function getMultiTable()
    {
        return $this->multiTable ?: static::setMultiTable()->multiTable;
    }
}