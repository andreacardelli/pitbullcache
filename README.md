# PitBullCache
PitBullCache is a fast and simple PHP Cache library with the minimum functionalities needed that abstracts different cache mechanisms. It is easy to use and implement with new cache mechanism.

With PitBullCache you can speed up web sites by caching any type of data: variables, queries or even full HTML pages.

Currently the cache systems abstracted by PitBullCache are:

- File System: uses file system to save data saved in a directory containing the serialized data.
- Redis (Predis\Client)
- Amazon S3 (tpyo/amazon-s3-php-class)

Method implemented are:
- Fetch key
- Store key
- Delete key

#Installation
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

We can use any type of cache, here an example with "file" type for caching full HTML page
```php
if(class_exists("Pitbull_Cache")){
	Pitbull_Cache::Register("file","Pitbull_Filesystem_Cache",dirname(__FILE__) . "/pitbullcache.cache/");
	$_cache = Pitbull_Cache::Create("file");
  	
	// here you can define any type ok key, must be unique of course
	$idpagecache = md5($_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
	
	if ($page=$_cache->fetch($idpagecache)) {
		echo $page;
		exit();
	}
}
```
and for storing keys...
```php
if(class_exists("Pitbull_Cache")){
	// caching full html $page for 1 days (86400 seconds)
	$_cache->store($idpagecache,$page,86400);
}
```

