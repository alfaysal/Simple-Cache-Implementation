<?php

namespace CacheImplementation;

use Psr\SimpleCache\CacheInterface;
use CacheImplementation\InvalidCacheArguments;
use CacheImplementation\CacheException;
use Psr\SimpleCache\InvalidArgumentException;

final class ArrayCache implements CacheInterface {

    private array $cache_dictionary = [];

    private string $valid_char_for_keys = "a-z,A-Z,.,_";

    private string $key_type;
    
    private int $key_length = 64;

    private string $cache_meta_key_name = 'key';
    private string $cache_meta_value_name = 'value';
    private string $cache_meta_expiration_name = 'expiration';

    /**
     * Fetches a value from the cache.
     *
     * @param string $key     The unique key of this item in the cache.
     * @param mixed  $default Default value to return if the key does not exist.
     *
     * @return mixed The value of the item from the cache, or $default in case of cache miss.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function get(string $key, mixed $default = null): mixed {
        $this->validateKey($key);

        return array_key_exists($key, $this->cache_dictionary) ? $this->getCacheItemValue($key) : $default;
    }

    /**
     * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
     *
     * @param string                 $key   The key of the item to store.
     * @param mixed                  $value The value of the item to store, must be serializable.
     * @param null|int|\DateInterval $ttl   Optional. The TTL value of this item. If no value is sent and
     *                                      the driver supports TTL then the library may set a default value
     *                                      for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function set(string $key, mixed $value = null, null|int|\DateInterval $ttl = null): bool {
        
        $this->validateKey($key);
        $time = $this->getTTL($ttl);

        $this->save([
            $this->cache_meta_key_name => $key,
            $this->cache_meta_value_name => $value,
            $this->cache_meta_expiration_name => $time
        ]);

        return true;
    }

    /**
     * Delete an item from the cache by its unique key.
     *
     * @param string $key The unique cache key of the item to delete.
     *
     * @return bool True if the item was successfully removed. False if there was an error.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function delete(string $key): bool {
        $this->validateKey($key);

        if (array_key_exists($key, $this->cache_dictionary)) {
            unset($this->cache_dictionary[$key]);

            return true;
        }

        return false;
    }

    /**
     * Wipes clean the entire cache's keys.
     *
     * @return bool True on success and false on failure.
     */
    public function clear(): bool {
        
        $this->cache_dictionary = [];

        return !empty($this->cache_dictionary);
    }

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param iterable<string> $keys    A list of keys that can be obtained in a single operation.
     * @param mixed            $default Default value to return for keys that do not exist.
     *
     * @return iterable<string, mixed> A list of key => value pairs. Cache keys that do not exist or are stale will have $default as value.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $keys is neither an array nor a Traversable,
     *   or if any of the $keys are not a legal value.
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable {
        $this->iterableValidate($keys);

        $results = [];

        foreach($keys as $key) {
            $this->validateKey($key);
            if (! array_key_exists($key, $this->cache_dictionary)) {
                throw new InvalidCacheArguments("your key is not found in cache");
            }
            
            $results[$key] = $this->getCacheItemValue($key);
        }

        return $results;
    }

    /**
     * Persists a set of key => value pairs in the cache, with an optional TTL.
     *
     * @param iterable               $values A list of key => value pairs for a multiple-set operation.
     * @param null|int|\DateInterval $ttl    Optional. The TTL value of this item. If no value is sent and
     *                                       the driver supports TTL then the library may set a default value
     *                                       for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $values is neither an array nor a Traversable,
     *   or if any of the $values are not a legal value.
     */
    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool {
        $this->iterableValidate($values);

        $time = $this->getTTl($ttl);

        foreach($values as $key => $value) {
            $this->validateKey($key);
            $this->save([
                $this->cache_meta_key_name => $key,
                $this->cache_meta_value_name => $value,
                $this->cache_meta_expiration_name => $time
            ]);
        }

        return true;
    }

    /**
     * Deletes multiple cache items in a single operation.
     *
     * @param iterable<string> $keys A list of string-based keys to be deleted.
     *
     * @return bool True if the items were successfully removed. False if there was an error.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $keys is neither an array nor a Traversable,
     *   or if any of the $keys are not a legal value.
     */
    public function deleteMultiple(iterable $keys): bool {
        $this->iterableValidate($keys);

        if (empty($this->cache_dictionary)) {

            return true;
        }

        foreach($this->cache_dictionary as $key => $value) {
            $this->validateKey($key);

            if (! array_key_exists($key, $this->cache_dictionary)) {
                throw new CacheException("Cache key is not exist. Given key {$key}");
            }

            unset($this->cache_dictionary[$key]);
        }

        return true;
    }

    /**
     * Determines whether an item is present in the cache.
     *
     * NOTE: It is recommended that has() is only to be used for cache warming type purposes
     * and not to be used within your live applications operations for get/set, as this method
     * is subject to a race condition where your has() will return true and immediately after,
     * another script can remove it making the state of your app out of date.
     *
     * @param string $key The cache item key.
     *
     * @return bool
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function has(string $key): bool {
        $this->validateKey($key);

        return array_key_exists($key, $this->cache_dictionary);
    }

    private function validateKey($key) : void {
        if (empty($key)) {
            throw new InvalidCacheArguments("Cache key can not be empty");
        }

        $this->key_type = gettype($key);

        if (! $this->key_type  == "string") {
            throw new InvalidCacheArguments("Cache key must be string. {$this->key_type} given");
        }

        if (preg_match("/[^a-zA-Z\._]/", $key)) {
            throw new InvalidCacheArguments("Cache key is not valid. Key must contain character {$this->valid_char_for_keys} given"); 
        }

        if (strlen($key) > $this->key_length ) {
            throw new InvalidCacheArguments("Cache key length should be smaller than {$this->key_length}"); 
        }
    }

    private function iterableValidate($values) : void {
        $get_value_type = gettype($values);

        if (! is_iterable($values)) {
            throw new CacheException("Value is not iterable type. {$get_value_type}");
        }
    }

    private function getCacheItemValue($key) {
        $expiration_time = $this->cache_dictionary[$key][$this->cache_meta_expiration_name];
        $value = $this->cache_dictionary[$key][$this->cache_meta_value_name];

        if (is_null($expiration_time)) {

            return $value;
        }

        if (time() <= $expiration_time) {

            return $value;
        }
        
        return null;
    }

    private function save(array $item) { 

        $this->cache_dictionary[$item[$this->cache_meta_key_name]] = [
            $this->cache_meta_value_name => $item[$this->cache_meta_value_name],
            $this->cache_meta_expiration_name => $item[$this->cache_meta_expiration_name]
        ];
    }

    private function getTTl($ttl) {

        if (is_null($ttl)) {
            return $ttl;
        }

        if ($ttl instanceof \DateInterval) {
            $time = time() + date_create('@0')->add($ttl)->getTimestamp();
        } elseif (is_numeric($ttl)) {
            $time = time() + $ttl;
        } else {
            throw new InvalidCacheArguments("ttl value is not valid. Expected value (null|int|\DateInterval)");
        }

        return $time;
    }

}