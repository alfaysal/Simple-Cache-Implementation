<?php

namespace CacheImplementation\DTO;

use CacheImplementation\InvalidCacheArgumentsException;
use CacheImplementation\CacheException;


class ItemDTO {
    public string $valid_char_for_keys = "a-z,A-Z,.,_";
    public int $key_length = 64;
    public string $key_type;
    public string $cache_meta_key_name = 'key';
    public string $cache_meta_value_name = 'value';
    public string $cache_meta_expiration_name = 'expiration';
    public string $key;
    public string $value;
    public string $expiration;

    public function validateKey($key) : void {
        if (empty($key)) {
            throw new InvalidCacheArgumentsException("Cache key can not be empty");
        }

        $this->key_type = gettype($key);

        if (! $this->key_type  === "string") {
            throw new InvalidCacheArgumentsException("Cache key must be string. {$this->key_type} given");
        }

        if (preg_match("/[^a-zA-Z\._]/", $key)) {
            throw new InvalidCacheArgumentsException("Cache key is not valid. Key must contain character {$this->valid_char_for_keys} given"); 
        }

        if (strlen($key) > $this->key_length ) {
            throw new InvalidCacheArgumentsException("Cache key length should be smaller than {$this->key_length}"); 
        }
    }

    public function getTTl($ttl) {

        if (is_null($ttl)) {
            return $ttl;
        }

        if ($ttl instanceof \DateInterval) {
            $time = time() + date_create('@0')->add($ttl)->getTimestamp();
        } elseif (is_numeric($ttl)) {
            $time = time() + $ttl;
        } else {
            throw new InvalidCacheArgumentsException("ttl value is not valid. Expected value (null|int|\DateInterval)");
        }

        return $time;
    }

    public function iterableValidate($values) : void {
        $get_value_type = gettype($values);

        if (! is_iterable($values)) {
            throw new CacheException("Value is not iterable type. {$get_value_type}");
        }
    }
    
}