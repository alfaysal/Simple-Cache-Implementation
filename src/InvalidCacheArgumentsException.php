<?php

namespace CacheImplementation;

use Psr\SimpleCache\InvalidArgumentException as InvalidArgumentException;

class InvalidCacheArgumentsException extends \Exception implements InvalidArgumentException {
    
}