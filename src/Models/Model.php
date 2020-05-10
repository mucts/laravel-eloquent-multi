<?php


namespace MuCTS\Laravel\EloquentMulti\Models;


use Grimzy\LaravelMysqlSpatial\Types\LineString;
use Grimzy\LaravelMysqlSpatial\Types\Point;
use Grimzy\LaravelMysqlSpatial\Types\Polygon;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use MuCTS\Laravel\EloquentMulti\Concerns\MultiHasRelationships;

class Model extends EloquentModel
{
    use Multi, MultiHasRelationships;

    public function getTable()
    {
        return $this->isMulti ? static::getMultiTable() : parent::getTable();
    }

    protected function castAttribute($key, $value)
    {
        if (is_null($value)) {
            return $value;
        }
        switch ($this->getCastType($key)) {
            case 'point' :
                if ($value instanceof Point) {
                    return ['lat' => $value->getLat(), 'lon' => $value->getLng()];
                }
                return $value;
            case 'polygon':
                if ($value instanceof Polygon) {
                    return collect($value->getLineStrings())
                        ->map(function (LineString $lineString) {
                            return collect($lineString->getPoints())
                                ->map(function (Point $point) {
                                    return ['lat' => $point->getLat(), 'lon' => $point->getLng()];
                                })
                                ->toArray();
                        })
                        ->toArray();
                }
                return $value;
        }
        return parent::castAttribute($key, $value);
    }

    public function setAttribute($key, $value)
    {
        parent::setAttribute($key, $value);
        if (!$this->hasCast($key)) {
            return $this;
        }
        if ($this->getCastType($key) == 'point'
            && is_array($value)
            && array_key_exists('lat', $value)
            && array_key_exists('lon', $value)) {
            $this->attributes[$key] = new Point($value['lat'], $value['lon']);
        } elseif ($this->getCastType($key) == 'polygon' && is_array($value)) {
            $lineString = [];
            collect($value)->each(function ($giss) use (&$lineString) {
                $points = collect($giss)
                    ->filter(function ($point) {
                        return is_array($point) && isset($point['lat']) && isset($point['lon']);
                    })->map(function ($point) {
                        return new Point($point['lat'], $point['lon']);
                    });
                if ($points->first() != $points->last()) {
                    $points->push($points->first());
                }
                array_push($lineString,
                    new LineString($points->toArray())
                );
            });
            $this->attributes[$key] = new Polygon($lineString);
        }

        return $this;
    }
}