<?php

namespace CacheImplementation;

use Psr\SimpleCache\InvalidArgumentException as InvalidArgumentException;

class InvalidCacheArguments extends \Exception implements InvalidArgumentException {
    
}