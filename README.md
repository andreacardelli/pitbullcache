# PitBullCache
PitBullCache is a fast and simple PHP Cache library with the minimum functionalities needed that abstracts different cache mechanisms. It is easy to use and easy to implement with new cache mechanism (MemCache,...).

With PitBullCache you can speed up web sites by caching any type of data: variables, queries or even full HTML pages.

Currently the cache systems abstracted by PitBullCache are:

- File System: uses file system to save data in a directory containing the serialized data one file for each unique key
- Redis (Predis\Client)
- Amazon S3 (tpyo/amazon-s3-php-class)

Implemented methods are:
- Fetch key
- Store key
- Delete key
- cleanUpExpired (useful jsut for file system)

Installation
=============
### Composer
PitBullCache supports composer, just add the packagist dependency: 
```javascript
{
    "require": {
    	  "andreacardelli/pitbullcache": "dev-master"
    }
}
```

How to use it
=============

We can use any type of cache, here an example with "file" type for caching full HTML page, third parameters contains configuration used by each low level storage (file: directory, redis: standard config for predis\client, S3: key and secret)

Define your cache (multiple cache for different purposes are allowed)
```php

Pitbull_Cache::Register("file","Pitbull_Filesystem_Cache","/pitbullcache.cache/");
$_cache = Pitbull_Cache::Create("file");
```
define your unique key 
```php
$idpagecache = md5($_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
```
retrieve the cached value of your key if it exists and if it is still valid otherwise you get false
```php
if ($page=$_cache->fetch($idpagecache)) {
	echo $page;
	exit();
}
```
and for storing keys...
```php
// caching full html $page for 1 day (86400 seconds)
$_cache->store($idpagecache,$page,86400);
```
(optional) for files sytem cleaning and S3 we added a class to be called with a batch job (cron) for other type of cache storage it just returns the number of cached active objects
```php
$_cache->cleanUpExpired('array'); // or 'json' to get back number of deleted items
```
