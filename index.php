<?php

require 'vendor/autoload.php';

use CacheImplementation\ArrayCache;

$cache = new ArrayCache();
// $cache->clear();
$cache->set("first_key", "first_value", 5);
$cache->set("second_key", "second_value", 5);
sleep(9);
// $cache->delete("sec.nd_key");
// $cache->delete("first_key");
var_dump($cache->get('first_key'));
var_dump($cache->get('second_key'));
// var_dump($cache->get('second_key'));

// $cache->setMultiple([
//     'first' => 678,
//     'second' => 'not_today',
// ],10);
// sleep(11);
// var_dump($cache->getMultiple(['first', 'second']));
// $cache->deleteMultiple(['first', 'second']);
// var_dump($cache->get('first'));
// var_dump($cache->get('second'));
