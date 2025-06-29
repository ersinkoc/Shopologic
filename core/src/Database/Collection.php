<?php

declare(strict_types=1);

namespace Shopologic\Core\Database;

class Collection implements \ArrayAccess, \Countable, \IteratorAggregate, \JsonSerializable
{
    protected array $items = [];

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public function all(): array
    {
        return $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    public function first(?callable $callback = null, $default = null)
    {
        if ($callback === null) {
            if (empty($this->items)) {
                return $default;
            }
            
            return reset($this->items);
        }

        foreach ($this->items as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return $default;
    }

    public function last(?callable $callback = null, $default = null)
    {
        if ($callback === null) {
            return empty($this->items) ? $default : end($this->items);
        }

        return $this->reverse()->first($callback, $default);
    }

    public function get($key, $default = null)
    {
        if ($this->offsetExists($key)) {
            return $this->items[$key];
        }

        return $default;
    }

    public function has($key): bool
    {
        return $this->offsetExists($key);
    }

    public function push(...$values): self
    {
        foreach ($values as $value) {
            $this->items[] = $value;
        }

        return $this;
    }

    public function put($key, $value): self
    {
        $this->items[$key] = $value;

        return $this;
    }

    public function forget($keys): self
    {
        foreach ((array) $keys as $key) {
            $this->offsetUnset($key);
        }

        return $this;
    }

    public function pull($key, $default = null)
    {
        $value = $this->get($key, $default);
        $this->forget($key);

        return $value;
    }

    public function map(callable $callback): self
    {
        $keys = array_keys($this->items);
        $items = array_map($callback, $this->items, $keys);

        return new static(array_combine($keys, $items));
    }

    public function filter(?callable $callback = null): self
    {
        if ($callback) {
            return new static(array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH));
        }

        return new static(array_filter($this->items));
    }

    public function reject(callable $callback): self
    {
        return $this->filter(function ($value, $key) use ($callback) {
            return !$callback($value, $key);
        });
    }

    public function values(): self
    {
        return new static(array_values($this->items));
    }

    public function keys(): self
    {
        return new static(array_keys($this->items));
    }

    public function each(callable $callback): self
    {
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }

        return $this;
    }

    public function reduce(callable $callback, $initial = null)
    {
        return array_reduce($this->items, $callback, $initial);
    }

    public function sort(?callable $callback = null): self
    {
        $items = $this->items;

        $callback ? uasort($items, $callback) : asort($items);

        return new static($items);
    }

    public function sortBy($callback, int $options = SORT_REGULAR, bool $descending = false): self
    {
        $results = [];

        $callback = $this->valueRetriever($callback);

        foreach ($this->items as $key => $value) {
            $results[$key] = $callback($value, $key);
        }

        $descending ? arsort($results, $options) : asort($results, $options);

        foreach (array_keys($results) as $key) {
            $results[$key] = $this->items[$key];
        }

        return new static($results);
    }

    public function sortByDesc($callback, int $options = SORT_REGULAR): self
    {
        return $this->sortBy($callback, $options, true);
    }

    public function reverse(): self
    {
        return new static(array_reverse($this->items, true));
    }

    public function chunk(int $size): self
    {
        if ($size <= 0) {
            return new static;
        }

        $chunks = [];

        foreach (array_chunk($this->items, $size, true) as $chunk) {
            $chunks[] = new static($chunk);
        }

        return new static($chunks);
    }

    public function collapse(): self
    {
        $results = [];

        foreach ($this->items as $values) {
            if ($values instanceof self) {
                $values = $values->all();
            } elseif (!is_array($values)) {
                continue;
            }

            $results = array_merge($results, $values);
        }

        return new static($results);
    }

    public function flatten(int $depth = INF): self
    {
        return new static($this->arrayFlatten($this->items, $depth));
    }

    protected function arrayFlatten(array $array, int $depth): array
    {
        $result = [];

        foreach ($array as $item) {
            if (!is_array($item)) {
                $result[] = $item;
            } elseif ($depth === 1) {
                $result = array_merge($result, array_values($item));
            } else {
                $result = array_merge($result, $this->arrayFlatten($item, $depth - 1));
            }
        }

        return $result;
    }

    public function pluck($value, $key = null): self
    {
        $results = [];

        $value = $this->valueRetriever($value);
        $key = $this->valueRetriever($key);

        foreach ($this->items as $item) {
            $itemValue = $value($item);

            if ($key === null) {
                $results[] = $itemValue;
            } else {
                $results[$key($item)] = $itemValue;
            }
        }

        return new static($results);
    }

    public function only($keys): self
    {
        if ($keys === null) {
            return new static($this->items);
        }

        $keys = is_array($keys) ? $keys : func_get_args();

        return new static(array_intersect_key($this->items, array_flip($keys)));
    }

    public function except($keys): self
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        return new static(array_diff_key($this->items, array_flip($keys)));
    }

    public function merge($items): self
    {
        return new static(array_merge($this->items, $this->getArrayableItems($items)));
    }

    public function contains($key, $operator = null, $value = null): bool
    {
        if (func_num_args() === 1) {
            if ($this->useAsCallable($key)) {
                $placeholder = new \stdClass;
                return $this->first($key, $placeholder) !== $placeholder;
            }

            return in_array($key, $this->items);
        }

        return $this->contains($this->operatorForWhere(...func_get_args()));
    }

    public function unique($key = null, bool $strict = false): self
    {
        if ($key === null) {
            return new static(array_unique($this->items, SORT_REGULAR));
        }

        $callback = $this->valueRetriever($key);
        $exists = [];

        return $this->reject(function ($item) use ($callback, $strict, &$exists) {
            $id = $callback($item);

            if (in_array($id, $exists, $strict)) {
                return true;
            }

            $exists[] = $id;
        });
    }

    public function groupBy($groupBy, bool $preserveKeys = false): self
    {
        $groupBy = $this->valueRetriever($groupBy);
        $results = [];

        foreach ($this->items as $key => $value) {
            $groupKeys = $groupBy($value, $key);

            if (!is_array($groupKeys)) {
                $groupKeys = [$groupKeys];
            }

            foreach ($groupKeys as $groupKey) {
                $groupKey = is_bool($groupKey) ? (int) $groupKey : $groupKey;

                if (!array_key_exists($groupKey, $results)) {
                    $results[$groupKey] = new static;
                }

                $results[$groupKey]->offsetSet($preserveKeys ? $key : null, $value);
            }
        }

        return new static($results);
    }

    public function toArray(): array
    {
        return array_map(function ($value) {
            return $value instanceof Model ? $value->toArray() : $value;
        }, $this->items);
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    public function offsetExists($key): bool
    {
        return array_key_exists($key, $this->items);
    }

    public function offsetGet($key): mixed
    {
        return $this->items[$key];
    }

    public function offsetSet($key, $value): void
    {
        if ($key === null) {
            $this->items[] = $value;
        } else {
            $this->items[$key] = $value;
        }
    }

    public function offsetUnset($key): void
    {
        unset($this->items[$key]);
    }

    protected function getArrayableItems($items): array
    {
        if (is_array($items)) {
            return $items;
        } elseif ($items instanceof self) {
            return $items->all();
        } elseif ($items instanceof \JsonSerializable) {
            return $items->jsonSerialize();
        } elseif ($items instanceof \Traversable) {
            return iterator_to_array($items);
        }

        return (array) $items;
    }

    protected function valueRetriever($value): callable
    {
        if ($this->useAsCallable($value)) {
            return $value;
        }

        return function ($item) use ($value) {
            return data_get($item, $value);
        };
    }

    protected function useAsCallable($value): bool
    {
        return !is_string($value) && is_callable($value);
    }

    protected function operatorForWhere($key, $operator = null, $value = null): callable
    {
        if (func_num_args() === 1) {
            $value = true;
            $operator = '=';
        }

        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        return function ($item) use ($key, $operator, $value) {
            $retrieved = data_get($item, $key);

            switch ($operator) {
                case '=':
                case '==':
                    return $retrieved == $value;
                case '!=':
                case '<>':
                    return $retrieved != $value;
                case '<':
                    return $retrieved < $value;
                case '>':
                    return $retrieved > $value;
                case '<=':
                    return $retrieved <= $value;
                case '>=':
                    return $retrieved >= $value;
                case '===':
                    return $retrieved === $value;
                case '!==':
                    return $retrieved !== $value;
                default:
                    return false;
            }
        };
    }
}