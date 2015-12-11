# php-vcache
version cache by php

## Example below
```php
$cache = new VCache([
	'host' => '127.0.0.1',
	'port' => '127.0.0.1',
	'timeout' => 5,
	'expire' => 900,
	'prefix' => 'test',
]);
$version = 'version';
$prefix = 'prefix';
$versionKey = $cache->getVersionKey([
	'user' => 'seyo',
	'phone' => '1234',
	'page' => 1,
], $version, ['user', 'phone']);
 
$data = $cache->getCache($prefix, 'key_aa', $versionKey);
if ($data) {
	var_dump('get cache');
	var_dump($data);
} else {
	var_dump('no cache');
	$data = 'value_bb';
	$cache->setCache($prefix, 'key_aa', $data, $versionKey);
}
 
var_dump('incrVersionNum');
$cache->incrVersionNum($prefix, $versionKey);
```
