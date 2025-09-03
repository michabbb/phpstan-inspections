<?php declare(strict_types=1);

namespace MissingIssetImplementationTrigger;

// Positive case: Implements ArrayAccess but misses __isset()
class MissingIsset implements \ArrayAccess
{
    private array $container = [];

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_null($offset)) {
            $this->container[] = $value;
        } else {
            $this->container[$offset] = $value;
        }
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->container[$offset]);
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->container[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->container[$offset] ?? null;
    }
}

// Negative case: Implements ArrayAccess and has __isset()
class HasIsset implements \ArrayAccess
{
    private array $container = [];

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_null($offset)) {
            $this->container[] = $value;
        } else {
            $this->container[$offset] = $value;
        }
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->container[$offset]);
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->container[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->container[$offset] ?? null;
    }

    public function __isset(mixed $offset): bool
    {
        return isset($this->container[$offset]);
    }
}

// Negative case: Does not implement ArrayAccess
class NoArrayAccess
{
    public function foo(): void
    {
        // Some method
    }
}
