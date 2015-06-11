<?php

namespace Playbloom\Bundle\GuzzleBundle\DataCollector;

use ArrayAccess;
use GuzzleHttp\Query;

class Guzzle5Query implements ArrayAccess
{
    private $queryArray;
    private $queryString;

    public function __construct(Query $query)
    {
        $this->queryArray = $query->toArray();
        $this->queryString = $query->__toString();
    }

    public function __toString()
    {
        return $this->queryString;
    }

    public function offsetExists($offset)
    {
        return isset($this->queryArray[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->queryArray[$offset];
    }

    public function offsetSet($offset, $value)
    {
        $this->queryArray[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->queryArray[$offset]);
    }
}
