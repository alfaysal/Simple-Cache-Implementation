<?php

namespace CacheImplementation;

use Psr\SimpleCache\CacheException as SimpleCacheCacheException;

class CacheException extends \Exception implements SimpleCacheCacheException {
    
}