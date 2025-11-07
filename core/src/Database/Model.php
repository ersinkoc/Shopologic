<?php

declare(strict_types=1);

namespace Shopologic\Core\Database;

use Shopologic\Core\Events\EventManager;
use Shopologic\Core\Database\Collection;
use Shopologic\Core\Database\Relations\Relation;
use Shopologic\Core\Database\Relations\HasMany;
use Shopologic\Core\Database\Relations\HasOne;
use Shopologic\Core\Database\Relations\BelongsTo;
use Shopologic\Core\Database\Relations\BelongsToMany;
use Shopologic\Core\Database\Relations\MorphMany;
use Shopologic\Core\Database\Relations\MorphOne;
use Shopologic\Core\Database\Relations\MorphTo;

abstract class Model
{
    protected ConnectionInterface $connection;
    protected static ?EventManager $eventManager = null;
    
    protected string $table = '';
    protected string $primaryKey = 'id';
    protected string $keyType = 'int';
    protected bool $incrementing = true;
    
    protected array $fillable = [];
    protected array $guarded = ['*'];
    protected array $hidden = [];
    protected array $visible = [];
    protected array $casts = [];
    
    protected array $attributes = [];
    protected array $original = [];
    protected array $changes = [];
    protected array $relations = [];
    
    protected bool $exists = false;
    protected bool $wasRecentlyCreated = false;
    
    protected bool $timestamps = true;
    protected string $createdAt = 'created_at';
    protected string $updatedAt = 'updated_at';
    
    protected ?string $deletedAt = null;
    protected bool $useSoftDeletes = false;
    
    protected array $with = [];
    protected array $withCount = [];
    
    protected static array $booted = [];
    protected static array $globalScopes = [];
    protected static array $observers = [];

    public function __construct(array $attributes = [])
    {
        $this->bootIfNotBooted();
        $this->syncOriginal();
        $this->fill($attributes);
    }

    protected function bootIfNotBooted(): void
    {
        if (!isset(static::$booted[static::class])) {
            static::booting();
            static::boot();
            static::booted();
            static::$booted[static::class] = true;
        }
    }

    protected static function booting(): void
    {
        // Override in child classes
    }

    protected static function boot(): void
    {
        // Override in child classes for trait boots
    }

    protected static function booted(): void
    {
        // Override in child classes
    }

    public function getTable(): string
    {
        if ($this->table) {
            return $this->table;
        }

        return str_replace('\\', '', snake_case(plural_case(class_basename($this))));
    }

    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    public function getKey(): mixed
    {
        return $this->getAttribute($this->getKeyName());
    }

    public function getKeyType(): string
    {
        return $this->keyType;
    }

    public function getIncrementing(): bool
    {
        return $this->incrementing;
    }

    public function getAttribute(string $key): mixed
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->getAttributeValue($key);
        }

        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        if (method_exists($this, $key)) {
            return $this->getRelationValue($key);
        }

        return null;
    }

    protected function getAttributeValue(string $key): mixed
    {
        $value = $this->attributes[$key] ?? null;

        if ($this->hasGetMutator($key)) {
            return $this->mutateAttribute($key, $value);
        }

        if ($this->hasCast($key)) {
            return $this->castAttribute($key, $value);
        }

        return $value;
    }

    public function setAttribute(string $key, mixed $value): self
    {
        if ($this->hasSetMutator($key)) {
            $method = 'set' . studly_case($key) . 'Attribute';
            return $this->{$method}($value);
        }

        $this->attributes[$key] = $value;
        return $this;
    }

    protected function hasGetMutator(string $key): bool
    {
        return method_exists($this, 'get' . studly_case($key) . 'Attribute');
    }

    protected function hasSetMutator(string $key): bool
    {
        return method_exists($this, 'set' . studly_case($key) . 'Attribute');
    }

    protected function mutateAttribute(string $key, mixed $value): mixed
    {
        return $this->{'get' . studly_case($key) . 'Attribute'}($value);
    }

    protected function hasCast(string $key): bool
    {
        return array_key_exists($key, $this->casts);
    }

    protected function castAttribute(string $key, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        switch ($this->getCastType($key)) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'real':
            case 'float':
            case 'double':
                return (float) $value;
            case 'decimal':
                $parts = explode(':', $this->casts[$key], 2);
                $decimals = isset($parts[1]) ? (int) $parts[1] : 2;
                return $this->asDecimal($value, $decimals);
            case 'string':
                return (string) $value;
            case 'bool':
            case 'boolean':
                return $this->castBoolean($value);
            case 'array':
            case 'json':
                return $this->fromJson($value);
            case 'datetime':
                return $this->asDateTime($value);
            case 'date':
                return $this->asDate($value);
            case 'timestamp':
                return $this->asTimestamp($value);
            default:
                return $value;
        }
    }

    protected function getCastType(string $key): string
    {
        $cast = $this->casts[$key];
        return explode(':', $cast)[0];
    }

    protected function asDecimal(mixed $value, int $decimals): float
    {
        return round((float) $value, $decimals);
    }

    protected function fromJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        return json_decode($value, true) ?: [];
    }

    protected function asDateTime(mixed $value): \DateTime
    {
        if ($value instanceof \DateTime) {
            return $value;
        }

        return new \DateTime($value);
    }

    protected function asDate(mixed $value): string
    {
        return $this->asDateTime($value)->format('Y-m-d');
    }

    protected function asTimestamp(mixed $value): int
    {
        return $this->asDateTime($value)->getTimestamp();
    }

    protected function castBoolean(mixed $value): bool
    {
        $connection = $this->getConnection();
        $driver = $connection->getDriverName();
        
        // Handle database-specific boolean representations
        if ($driver === 'pgsql') {
            // PostgreSQL returns 't'/'f' or true/false
            if (is_string($value)) {
                return $value === 't' || $value === 'true';
            }
        } elseif ($driver === 'mysql') {
            // MySQL returns 1/0 or '1'/'0'
            if (is_string($value)) {
                return $value === '1';
            }
        }
        
        return (bool) $value;
    }

    public function fill(array $attributes): self
    {
        foreach ($this->fillableFromArray($attributes) as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this;
    }

    protected function fillableFromArray(array $attributes): array
    {
        if (count($this->fillable) > 0) {
            return array_intersect_key($attributes, array_flip($this->fillable));
        }

        return $attributes;
    }

    public function newQuery(): Builder
    {
        return (new Builder($this->getConnection()))
            ->setModel($this)
            ->from($this->getTable());
    }

    public static function query(): Builder
    {
        return (new static)->newQuery();
    }

    public function save(array $options = []): bool
    {
        $query = $this->newQuery();

        if ($this->fireModelEvent('saving') === false) {
            return false;
        }

        if ($this->exists) {
            $saved = $this->performUpdate($query, $options);
        } else {
            $saved = $this->performInsert($query, $options);
        }

        if ($saved) {
            $this->finishSave($options);
        }

        return $saved;
    }

    protected function performUpdate(Builder $query, array $options = []): bool
    {
        if ($this->fireModelEvent('updating') === false) {
            return false;
        }

        if ($this->timestamps) {
            $this->updateTimestamps();
        }

        $dirty = $this->getDirty();

        if (count($dirty) > 0) {
            $numRows = $query->where($this->getKeyName(), $this->getKey())
                            ->update($dirty);

            $this->syncChanges();

            $this->fireModelEvent('updated');

            return $numRows > 0;
        }

        return true;
    }

    protected function performInsert(Builder $query, array $options = []): bool
    {
        if ($this->fireModelEvent('creating') === false) {
            return false;
        }

        if ($this->timestamps) {
            $this->updateTimestamps();
        }

        $attributes = $this->attributes;

        if ($this->incrementing) {
            $id = $query->insertGetId($attributes);
            $this->setAttribute($this->getKeyName(), $id);
        } else {
            $query->insert($attributes);
        }

        $this->exists = true;
        $this->wasRecentlyCreated = true;

        $this->fireModelEvent('created');

        return true;
    }

    protected function finishSave(array $options = []): void
    {
        $this->fireModelEvent('saved');
        $this->syncOriginal();
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        if (!$this->exists) {
            return false;
        }

        return $this->fill($attributes)->save($options);
    }

    public function delete(): ?bool
    {
        if (!$this->exists) {
            return null;
        }

        if ($this->fireModelEvent('deleting') === false) {
            return false;
        }

        $this->performDelete();

        $this->fireModelEvent('deleted');

        return true;
    }

    protected function performDelete(): void
    {
        if ($this->useSoftDeletes) {
            $this->runSoftDelete();
        } else {
            $this->newQuery()->where($this->getKeyName(), $this->getKey())->delete();
        }

        $this->exists = false;
    }

    protected function runSoftDelete(): void
    {
        $this->setAttribute($this->deletedAt, new \DateTime());
        $this->save();
    }

    public function restore(): bool
    {
        if (!$this->useSoftDeletes) {
            return false;
        }

        if ($this->fireModelEvent('restoring') === false) {
            return false;
        }

        $this->setAttribute($this->deletedAt, null);
        $this->save();

        $this->fireModelEvent('restored');

        return true;
    }

    protected function updateTimestamps(): void
    {
        $time = $this->freshTimestamp();

        if (!$this->exists && $this->timestamps) {
            $this->setAttribute($this->createdAt, $time);
        }

        if ($this->timestamps) {
            $this->setAttribute($this->updatedAt, $time);
        }
    }

    protected function freshTimestamp(): string
    {
        $connection = $this->getConnection();
        $driver = $connection->getDriverName();
        
        // Return database-specific timestamp format
        if ($driver === 'pgsql') {
            return (new \DateTime())->format('Y-m-d H:i:s.u');
        } else {
            return (new \DateTime())->format('Y-m-d H:i:s');
        }
    }

    public function getDirty(): array
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $value !== $this->original[$key]) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    public function syncOriginal(): self
    {
        $this->original = $this->attributes;
        return $this;
    }

    public function syncChanges(): self
    {
        $this->changes = $this->getDirty();
        return $this;
    }

    protected function getRelationValue(string $key): mixed
    {
        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        if (!method_exists($this, $key)) {
            return null;
        }

        return $this->getRelationshipFromMethod($key);
    }

    protected function getRelationshipFromMethod(string $method): mixed
    {
        $relation = $this->$method();

        if (!$relation instanceof Relation) {
            throw new \RuntimeException("Relationship method {$method} must return a Relation instance.");
        }

        return $this->relations[$method] = $relation->getResults();
    }

    /**
     * Check if a relationship has been loaded
     */
    public function relationLoaded(string $relation): bool
    {
        return array_key_exists($relation, $this->relations);
    }

    /**
     * Get a loaded relationship
     */
    public function getRelation(string $relation): mixed
    {
        return $this->relations[$relation] ?? null;
    }

    /**
     * Set a relationship on the model
     */
    public function setRelation(string $name, mixed $value): self
    {
        $this->relations[$name] = $value;
        return $this;
    }

    /**
     * Unset a relationship on the model
     */
    public function unsetRelation(string $relation): self
    {
        unset($this->relations[$relation]);
        return $this;
    }

    /**
     * Get all the loaded relations
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    /**
     * Set multiple relations at once
     */
    public function setRelations(array $relations): self
    {
        $this->relations = $relations;
        return $this;
    }

    public function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): HasMany
    {
        $instance = new $related;
        $foreignKey = $foreignKey ?: $this->getForeignKey();
        $localKey = $localKey ?: $this->getKeyName();

        return new HasMany($instance->newQuery(), $this, $instance->getTable() . '.' . $foreignKey, $localKey);
    }

    public function hasOne(string $related, ?string $foreignKey = null, ?string $localKey = null): HasOne
    {
        $instance = new $related;
        $foreignKey = $foreignKey ?: $this->getForeignKey();
        $localKey = $localKey ?: $this->getKeyName();

        return new HasOne($instance->newQuery(), $this, $instance->getTable() . '.' . $foreignKey, $localKey);
    }

    public function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null, ?string $relation = null): BelongsTo
    {
        if ($relation === null) {
            $relation = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];
        }

        $instance = new $related;
        $foreignKey = $foreignKey ?: $relation . '_id';
        $ownerKey = $ownerKey ?: $instance->getKeyName();

        return new BelongsTo($instance->newQuery(), $this, $foreignKey, $ownerKey, $relation);
    }

    public function belongsToMany(string $related, ?string $table = null, ?string $foreignPivotKey = null, ?string $relatedPivotKey = null, ?string $parentKey = null, ?string $relatedKey = null, ?string $relation = null): BelongsToMany
    {
        if ($relation === null) {
            $relation = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];
        }

        $instance = new $related;

        $foreignPivotKey = $foreignPivotKey ?: $this->getForeignKey();
        $relatedPivotKey = $relatedPivotKey ?: $instance->getForeignKey();
        $parentKey = $parentKey ?: $this->getKeyName();
        $relatedKey = $relatedKey ?: $instance->getKeyName();

        if ($table === null) {
            $table = $this->joiningTable($related, $instance);
        }

        return new BelongsToMany(
            $instance->newQuery(), $this, $table, $foreignPivotKey,
            $relatedPivotKey, $parentKey, $relatedKey, $relation
        );
    }

    protected function joiningTable(string $related, Model $instance): string
    {
        $segments = [
            snake_case(class_basename($this)),
            snake_case(class_basename($instance)),
        ];

        sort($segments);

        return implode('_', $segments);
    }

    public function getForeignKey(): string
    {
        return snake_case(class_basename($this)) . '_' . $this->getKeyName();
    }

    protected function fireModelEvent(string $event): mixed
    {
        if (!isset(static::$eventManager)) {
            return true;
        }

        $method = 'fire' . ucfirst($event);
        
        if (method_exists($this, $method)) {
            return $this->$method();
        }

        $eventClass = 'Shopologic\\Core\\Database\\Events\\' . ucfirst($event);
        if (class_exists($eventClass)) {
            $event = new $eventClass($this);
            static::$eventManager->dispatch($event);
            return !($event instanceof StoppableEventInterface && $event->isPropagationStopped());
        }

        return true;
    }

    public function getConnection(): ConnectionInterface
    {
        return $this->connection ?? app(DatabaseManager::class)->connection();
    }

    public function setConnection(ConnectionInterface $connection): self
    {
        $this->connection = $connection;
        return $this;
    }

    public static function setEventManager(EventManager $eventManager): void
    {
        static::$eventManager = $eventManager;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function toArray(): array
    {
        $attributes = $this->attributesToArray();
        $relations = $this->relationsToArray();

        return array_merge($attributes, $relations);
    }

    protected function attributesToArray(): array
    {
        $attributes = $this->attributes;

        foreach ($this->hidden as $hidden) {
            unset($attributes[$hidden]);
        }

        if (count($this->visible) > 0) {
            $attributes = array_intersect_key($attributes, array_flip($this->visible));
        }

        foreach ($attributes as $key => &$value) {
            if ($this->hasGetMutator($key)) {
                $value = $this->mutateAttribute($key, $value);
            } elseif ($this->hasCast($key)) {
                $value = $this->castAttribute($key, $value);
            }
        }

        return $attributes;
    }

    protected function relationsToArray(): array
    {
        $relations = [];

        foreach ($this->relations as $key => $relation) {
            if (!in_array($key, $this->hidden)) {
                if ($relation instanceof Model) {
                    $relations[$key] = $relation->toArray();
                } elseif (is_array($relation) || $relation instanceof \Traversable) {
                    $relations[$key] = [];
                    foreach ($relation as $item) {
                        if ($item instanceof Model) {
                            $relations[$key][] = $item->toArray();
                        } else {
                            $relations[$key][] = $item;
                        }
                    }
                } else {
                    $relations[$key] = $relation;
                }
            }
        }

        return $relations;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Create a new collection instance
     */
    public function newCollection(array $models = []): Collection
    {
        return new Collection($models);
    }

    /**
     * Get the name of the created_at column
     */
    public function getCreatedAtColumn(): string
    {
        return $this->createdAt;
    }

    /**
     * Get the name of the updated_at column
     */
    public function getUpdatedAtColumn(): string
    {
        return $this->updatedAt;
    }

    /**
     * Get the name of the deleted_at column
     */
    public function getDeletedAtColumn(): ?string
    {
        return $this->deletedAt;
    }

    /**
     * Create a new instance of the model
     */
    public function newInstance(array $attributes = [], bool $exists = false): static
    {
        $model = new static($attributes);
        $model->exists = $exists;
        $model->setConnection($this->getConnection());
        return $model;
    }

    /**
     * Create a new model instance from raw attributes
     */
    public function newFromBuilder(array $attributes = [], ?string $connection = null): static
    {
        $model = $this->newInstance([], true);
        $model->setRawAttributes($attributes, true);

        if ($connection) {
            $model->setConnection($connection);
        }

        return $model;
    }

    /**
     * Set the raw attributes without any casting
     */
    public function setRawAttributes(array $attributes, bool $sync = false): self
    {
        $this->attributes = $attributes;

        if ($sync) {
            $this->syncOriginal();
        }

        return $this;
    }

    /**
     * Check if the model uses timestamps
     */
    public function usesTimestamps(): bool
    {
        return $this->timestamps;
    }

    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    public function __isset(string $key): bool
    {
        return $this->getAttribute($key) !== null;
    }

    public function __unset(string $key): void
    {
        unset($this->attributes[$key], $this->relations[$key]);
    }

    public static function __callStatic(string $method, array $parameters): mixed
    {
        return (new static)->$method(...$parameters);
    }

    public function __call(string $method, array $parameters): mixed
    {
        return $this->newQuery()->$method(...$parameters);
    }
}