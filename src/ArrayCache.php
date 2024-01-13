<?php

namespace CacheImplementation;

use Psr\SimpleCache\CacheInterface;
use CacheImplementation\InvalidCacheArgumentsException;
use CacheImplementation\CacheException;
use CacheImplementation\Item;

final class ArrayCache implements CacheInterface {

    private Item $item;
    private array $cache_dictionary = [];
    private static $instance;

    private function __construct() {
        $this->item = new Item();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }

    public function get(string $key, mixed $default = null): mixed {
        $this->item->validateKey($key);

        return array_key_exists($key, $this->cache_dictionary) ? $this->getCacheItemValue($key) : $default;
    }

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

    public function delete(string $key): bool {
        $this->item->validateKey($key);

        if (array_key_exists($key, $this->cache_dictionary)) {
            unset($this->cache_dictionary[$key]);

            return true;
        }

        return false;
    }

    public function clear(): bool {
        
        $this->cache_dictionary = [];

        return !empty($this->cache_dictionary);
    }

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