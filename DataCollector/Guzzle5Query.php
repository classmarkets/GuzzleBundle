<?php

namespace Playbloom\Bundle\GuzzleBundle\DataCollector;

use ArrayAccess;
use ArrayIterator;
use GuzzleHttp\Query;
use IteratorAggregate;

class Guzzle5Query implements ArrayAccess, IteratorAggregate
{
    private array $queryArray;
    private string $queryString;

    public function __construct(Query $query)
    {
        $this->queryArray = $query->toArray();
        $this->queryString = $query->__toString();
    }

    public function __toString(): string
    {
        return $this->queryString;
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->queryArray);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->queryArray[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->queryArray[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->queryArray[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->queryArray[$offset]);
    }
}
