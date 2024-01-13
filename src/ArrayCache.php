<?php

namespace CacheImplementation;

use Psr\SimpleCache\CacheInterface;
use CacheImplementation\InvalidCacheArgumentsException;
use CacheImplementation\CacheException;
use CacheImplementation\DTO\ItemDTO;

final class ArrayCache implements CacheInterface {

    private ItemDTO $item;
    private array $cache_dictionary = [];

    public function __construct() {
        $this->item = new ItemDTO();
    }

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
        $this->item->validateKey($key);

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
        
        $this->item->validateKey($key);
        $time = $this->item->getTTL($ttl);

        $this->save([
            $this->item->cache_meta_key_name => $key,
            $this->item->cache_meta_value_name => $value,
            $this->item->cache_meta_expiration_name => $time
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
        $this->item->validateKey($key);

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
        $this->item->iterableValidate($keys);

        $results = [];

        foreach($keys as $key) {
            $this->item->validateKey($key);
            if (! array_key_exists($key, $this->cache_dictionary)) {
                throw new InvalidCacheArgumentsException("your key is not found in cache");
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
        $this->item->iterableValidate($values);

        $time = $this->item->getTTl($ttl);

        foreach($values as $key => $value) {
            $this->item->validateKey($key);
            $this->save([
                $this->item->cache_meta_key_name => $key,
                $this->item->cache_meta_value_name => $value,
                $this->item->cache_meta_expiration_name => $time
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
        $this->item->iterableValidate($keys);

        if (empty($this->cache_dictionary)) {

            return true;
        }

        foreach($this->cache_dictionary as $key => $value) {
            $this->item->validateKey($key);

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
        $this->item->validateKey($key);

        return array_key_exists($key, $this->cache_dictionary);
    }

    private function getCacheItemValue($key) {
        $expiration_time = $this->cache_dictionary[$key][$this->item->cache_meta_expiration_name];
        $value = $this->cache_dictionary[$key][$this->item->cache_meta_value_name];

        if (is_null($expiration_time)) {

            return $value;
        }

        if (time() <= $expiration_time) {

            return $value;
        }
        
        return null;
    }

    private function save(array $item) { 

        $this->cache_dictionary[$item[$this->item->cache_meta_key_name]] = [
            $this->item->cache_meta_value_name => $item[$this->item->cache_meta_value_name],
            $this->item->cache_meta_expiration_name => $item[$this->item->cache_meta_expiration_name]
        ];
    }

}