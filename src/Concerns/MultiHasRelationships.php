<?php


namespace MuCTS\LaravelEloquentMulti\Concerns;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;
use MuCTS\LaravelEloquentMulti\Models\Model;

trait MultiHasRelationships
{
    /**
     * Define a one-to-one relationship.
     *
     * @param string $related
     * @param string|null $foreignKey
     * @param string|null $localKey
     * @param string|null $multiStr
     * @return HasOne
     */
    public function multiHasOne($related, $foreignKey = null, $localKey = null, $multiStr = null)
    {
        /** @var Model $instance */
        $instance = $this->newRelatedInstance($related);

        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $localKey = $localKey ?: $this->getKeyName();

        $multiStr = $multiStr ?: $this->{$foreignKey};
        /** @var Model $this */
        return $this->newHasOne($instance->setMultiTable($multiStr)->newQuery(), $this, $instance->getTable() . '.' . $foreignKey, $localKey);
    }


    /**
     * Define a has-one-through relationship.
     *
     * @param string $related
     * @param string $through
     * @param string|null $firstKey
     * @param string|null $secondKey
     * @param string|null $localKey
     * @param string|null $secondLocalKey
     * @param null $firstMultiStr
     * @param null $secondMultiStr
     * @return HasOneThrough
     */
    public function multiHasOneThrough($related, $through, $firstKey = null, $secondKey = null, $localKey = null, $secondLocalKey = null, $firstMultiStr = null, $secondMultiStr = null)
    {
        /** @var Model $through */
        $through = new $through;

        $firstKey = $firstKey ?: $this->getForeignKey();
        $firstMultiStr = $firstMultiStr ?: $this->{$firstKey};

        $secondKey = $secondKey ?: $through->getForeignKey();
        $secondMultiStr = $secondMultiStr ?: $this->{$secondKey};

        /** @var Model $this */
        return $this->newHasOneThrough(
            $this->newRelatedInstance($related)->setMultiTable($firstMultiStr)->newQuery(),
            $this,
            $through->setMultiTable($secondMultiStr),
            $firstKey,
            $secondKey,
            $localKey ?: $this->getKeyName(),
            $secondLocalKey ?: $through->getKeyName()
        );
    }

    /**
     * Define a polymorphic one-to-one relationship.
     *
     * @param string $related
     * @param string $name
     * @param string|null $type
     * @param string|null $id
     * @param string|null $localKey
     * @param string|null $multiStr
     * @return MorphOne
     */
    public function multiMorphOne($related, $name, $type = null, $id = null, $localKey = null, ?string $multiStr = null)
    {
        /** @var Model $instance */
        $instance = $this->newRelatedInstance($related);

        [$type, $id] = $this->getMorphs($name, $type, $id);

        $table = $instance->getTable();

        $localKey = $localKey ?: $this->getKeyName();

        $multiStr = $multiStr ?: $this->{$id};
        /** @var Model $this */
        return $this->newMorphOne($instance->setMultiTable($multiStr)->newQuery(), $this, $table . '.' . $type, $table . '.' . $id, $localKey);
    }


    /**
     * Define an inverse one-to-one or many relationship.
     *
     * @param string $related
     * @param string|null $foreignKey
     * @param string|null $ownerKey
     * @param string|null $relation
     * @param string|null $multiStr
     * @return BelongsTo
     */
    public function multiBelongsTo($related, $foreignKey = null, $ownerKey = null, $relation = null, ?string $multiStr = null)
    {

        if (is_null($relation)) {
            $relation = $this->guessBelongsToRelation();
        }
        /** @var Model $instance */
        $instance = $this->newRelatedInstance($related);

        if (is_null($foreignKey)) {
            $foreignKey = Str::snake($relation) . '_' . $instance->getKeyName();
        }

        $ownerKey = $ownerKey ?: $instance->getKeyName();

        $multiStr = $multiStr ?: $this->{$ownerKey};

        /** @var Model $this */
        return $this->newBelongsTo(
            $instance->setMultiTable($multiStr)->newQuery(), $this, $foreignKey, $ownerKey, $relation
        );
    }


    /**
     * Define a polymorphic, inverse one-to-one or many relationship.
     *
     * @param string|null $name
     * @param string|null $type
     * @param string|null $id
     * @param string|null $ownerKey
     * @param string|null $multiStr
     * @return MorphTo
     */
    public function multiMorphTo($name = null, $type = null, $id = null, $ownerKey = null, ?string $multiStr = null)
    {

        $name = $name ?: $this->guessBelongsToRelation();

        [$type, $id] = $this->getMorphs(
            Str::snake($name), $type, $id
        );

        return empty($class = $this->{$type})
            ? $this->multiMorphEagerTo($name, $type, $id, $ownerKey, $multiStr)
            : $this->multiMorphInstanceTo($class, $name, $type, $id, $ownerKey, $multiStr);
    }

    /**
     * Define a polymorphic, inverse one-to-one or many relationship.
     *
     * @param string $name
     * @param string $type
     * @param string $id
     * @param string $ownerKey
     * @param string|null $multiStr
     * @return MorphTo
     */
    protected function multiMorphEagerTo($name, $type, $id, $ownerKey, ?string $multiStr = null)
    {
        $multiStr = $multiStr ?: $this->{$id};
        /** @var Model $this */
        return $this->newMultiMorphTo(
            (clone $this)->setMultiTable($multiStr)->newQuery()->setEagerLoads([]), $this, $id, $ownerKey, $type, $name
        );
    }

    /**
     * Define a polymorphic, inverse one-to-one or many relationship.
     *
     * @param string $target
     * @param string $name
     * @param string $type
     * @param string $id
     * @param string $ownerKey
     * @param string|null $multiStr
     * @return MorphTo
     */
    protected function multiMorphInstanceTo($target, $name, $type, $id, $ownerKey, ?string $multiStr = null)
    {
        /** @var Model $instance */
        $instance = $this->newRelatedInstance(
            static::getActualClassNameForMorph($target)
        );

        $multiStr = $multiStr ?: $this->{$id};

        /** @var Model $this */
        return $this->newMultiMorphTo(
            $instance->setMultiTable($multiStr)->newQuery(), $this, $id, $ownerKey ?? $instance->getKeyName(), $type, $name
        );
    }

    /**
     * Instantiate a new MorphTo relationship.
     *
     * @param Builder $query
     * @param Model $parent
     * @param string $foreignKey
     * @param string $ownerKey
     * @param string $type
     * @param string $relation
     * @return MorphTo
     */
    protected function newMultiMorphTo(Builder $query, Model $parent, $foreignKey, $ownerKey, $type, $relation)
    {
        return new MorphTo($query, $parent, $foreignKey, $ownerKey, $type, $relation);
    }

    /**
     * Define a one-to-many relationship.
     *
     * @param string $related
     * @param string|null $foreignKey
     * @param string|null $localKey
     * @param string|null $multiStr
     * @return HasMany
     */
    public function multiHasMany($related, $foreignKey = null, $localKey = null, ?string $multiStr = null)
    {
        /** @var Model $instance */
        $instance = $this->newRelatedInstance($related);

        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $localKey = $localKey ?: $this->getKeyName();

        $multiStr = $multiStr ?: $this->{$foreignKey};
        /** @var Model $this */
        return $this->newHasMany(
            $instance->setMultiTable($multiStr)->newQuery(), $this, $instance->getTable() . '.' . $foreignKey, $localKey
        );
    }


    /**
     * Define a has-many-through relationship.
     *
     * @param string $related
     * @param string $through
     * @param string|null $firstKey
     * @param string|null $secondKey
     * @param string|null $localKey
     * @param string|null $secondLocalKey
     * @return HasManyThrough
     */
    public function multiHasManyThrough($related, $through, $firstKey = null, $secondKey = null, $localKey = null, $secondLocalKey = null, ?string $firstMultiStr = null, ?string $secondMultiStr = null)
    {
        /** @var Model $through */
        $through = new $through;

        $firstKey = $firstKey ?: $this->getForeignKey();
        $firstMultiStr = $firstMultiStr ?: $this->{$firstKey};

        $secondKey = $secondKey ?: $through->getForeignKey();
        $secondMultiStr = $secondMultiStr ?: $this->{$secondKey};
        /** @var Model $this */
        return $this->newHasManyThrough(
            $this->newRelatedInstance($related)->setMultiTable($firstMultiStr)->newQuery(), $this, $through->setMultiTable($secondMultiStr),
            $firstKey, $secondKey, $localKey ?: $this->getKeyName(),
            $secondLocalKey ?: $through->getKeyName()
        );
    }

    /**
     * Define a polymorphic one-to-many relationship.
     *
     * @param string $related
     * @param string $name
     * @param string|null $type
     * @param string|null $id
     * @param string|null $localKey
     * @return MorphMany
     */
    public function multiMorphMany($related, $name, $type = null, $id = null, $localKey = null)
    {
        /** @var Model $instance */
        $instance = $this->newRelatedInstance($related);

        [$type, $id] = $this->getMorphs($name, $type, $id);

        $table = $instance->getTable();

        $localKey = $localKey ?: $this->getKeyName();
        /** @var Model $this */
        return $this->newMorphMany($instance->setMultiTable($this->{$id})->newQuery(), $this, $table . '.' . $type, $table . '.' . $id, $localKey);
    }


    /**
     * Define a many-to-many relationship.
     *
     * @param string $related
     * @param string|null $table
     * @param string|null $foreignPivotKey
     * @param string|null $relatedPivotKey
     * @param string|null $parentKey
     * @param string|null $relatedKey
     * @param string|null $relation
     * @param null $multiStr
     * @return BelongsToMany
     */
    public function multiBelongsToMany($related, $table = null, $foreignPivotKey = null, $relatedPivotKey = null,
                                       $parentKey = null, $relatedKey = null, $relation = null, $multiStr = null)
    {
        if (is_null($relation)) {
            $relation = $this->guessBelongsToManyRelation();
        }

        /** @var Model $instance */
        $instance = $this->newRelatedInstance($related);

        $foreignPivotKey = $foreignPivotKey ?: $this->getForeignKey();
        $multiStr = $multiStr ?: $this->getForeignKey();

        $relatedPivotKey = $relatedPivotKey ?: $instance->getForeignKey();

        if (is_null($table)) {
            $table = $this->joiningTable($related, $instance);
        }
        /** @var Model $this */
        return $this->newBelongsToMany(
            $instance->setMultiTable($multiStr)->newQuery(), $this, $table, $foreignPivotKey,
            $relatedPivotKey, $parentKey ?: $this->getKeyName(),
            $relatedKey ?: $instance->getKeyName(), $relation
        );
    }


    /**
     * Define a polymorphic many-to-many relationship.
     *
     * @param string $related
     * @param string $name
     * @param string|null $table
     * @param string|null $foreignPivotKey
     * @param string|null $relatedPivotKey
     * @param string|null $parentKey
     * @param string|null $relatedKey
     * @param bool $inverse
     * @return MorphToMany
     */
    public function multiMorphToMany($related, $name, $table = null, $foreignPivotKey = null,
                                     $relatedPivotKey = null, $parentKey = null,
                                     $relatedKey = null, $inverse = false)
    {
        $caller = $this->guessBelongsToManyRelation();

        /** @var Model $instance */
        $instance = $this->newRelatedInstance($related);

        $foreignPivotKey = $foreignPivotKey ?: $name . '_id';

        $relatedPivotKey = $relatedPivotKey ?: $instance->getForeignKey();

        if (!$table) {
            $words = preg_split('/(_)/u', $name, -1, PREG_SPLIT_DELIM_CAPTURE);

            $lastWord = array_pop($words);

            $table = implode('', $words) . Str::plural($lastWord);
        }
        /** @var Model $this */
        return $this->newMorphToMany(
            $instance->setMultiTable($this->{$foreignPivotKey})->newQuery(), $this, $name, $table,
            $foreignPivotKey, $relatedPivotKey, $parentKey ?: $this->getKeyName(),
            $relatedKey ?: $instance->getKeyName(), $caller, $inverse
        );
    }


    /**
     * Define a polymorphic, inverse many-to-many relationship.
     *
     * @param string $related
     * @param string $name
     * @param string|null $table
     * @param string|null $foreignPivotKey
     * @param string|null $relatedPivotKey
     * @param string|null $parentKey
     * @param string|null $relatedKey
     * @return MorphToMany
     */
    public function morphedByMany($related, $name, $table = null, $foreignPivotKey = null,
                                  $relatedPivotKey = null, $parentKey = null, $relatedKey = null)
    {
        $foreignPivotKey = $foreignPivotKey ?: $this->getForeignKey();

        $relatedPivotKey = $relatedPivotKey ?: $name . '_id';

        return $this->multiMorphToMany(
            $related, $name, $table, $foreignPivotKey,
            $relatedPivotKey, $parentKey, $relatedKey, true
        );
    }
}