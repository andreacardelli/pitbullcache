<?php
// Classi per varie tipologie di cache con Dynamic Factories

abstract class Pitbull_Base_Cache
{
	abstract function __construct($config);
        abstract function fetch($key);
        abstract function store($key,$data,$ttl);
        abstract function delete($key);
}

class Pitbull_Filesystem_Cache extends Pitbull_Base_Cache
{

	function __construct($config) {
		$this->path = $config;
		if (!is_dir($this->path)) {
		    if (mkdir($this->path, 0777, true)) throw new Exception('Could not create cache directory'); ;
		    $h=fopen($this->path."/.gitgnore",'a+');
		    fwrite($h,'[^.]*');
		    fclose($h);
		}
	}
	 // This is the function you store information with
	function store($key,$data,$ttl) {

		// Opening the file in read/write mode
		$h = fopen($this->getFileName($key),'a+');
		if (!$h) throw new Exception('Could not open cache file');

		flock($h,LOCK_EX); // exclusive lock, will get released when the file is closed

		fseek($h,0); // go to the start of the file

		// truncate the file
		ftruncate($h,0);

		// Serializing along with the TTL
		$data = serialize(array(time()+$ttl,$data));
		if (fwrite($h,$data)===false) {
		  throw new Exception('Could not write to cache');
		}
		fclose($h);
	}

  	// The function to fetch data returns false on failure
	function fetch($key) {
		$filename = $this->getFileName($key);
		if (!file_exists($filename)) return false;
		$h = fopen($filename,'r');

		if (!$h) return false;

		// Getting a shared lock 
		flock($h,LOCK_SH);

		$data = file_get_contents($filename);
		fclose($h);

		$data = @unserialize($data);
		if (!$data) {
		 // If unserializing somehow didn't work out, we'll delete the file
		 unlink($filename);
		 return false;
		}

		if (time() > $data[0]) {
		 // Unlinking when the file was expired
		 unlink($filename);
		 return false;
		}
		return $data[1];
	}

	function delete( $key ) {
		$filename = $this->getFileName($key);
		if (file_exists($filename)) {
		  return unlink($filename);
		} else {
		  return false;
		}
	}

  	private function getFileName($key) {
      	return $this->path . md5($key);
  	}
}

class Pitbull_Redis_Cache extends Pitbull_Base_Cache
{
		function __construct ($config) {
			$this->cache = new Predis\Client($config);
		}
		function fetch($key) { 
			return json_decode($this->cache->get($key),true);;
		}
        function store($key,$data,$ttl) { 
        	$this->cache->set($key, json_encode($data));
        	$this->cache->expire($key,$ttl);
        	return $key;
        }
        function delete($key) {
        	return $this->cache->del($key);
    	}
}

abstract class Pitbull_DynamicFactory
{
	protected static $types = array();

	public static function Register($type, $class,$config)
	{
		self::$types[$type]=array('classname'=>$class,'config'=>$config);
	}

	public static function IsRegistered($type)
	{
		if (isset(self::$types[$type]))
			return true;
		else
			return false;
	}

	public static function Create($type)
	{
		if (isset(self::$types[$type]['classname']) && 
		class_exists(self::$types[$type]['classname']))
			return new self::$types[$type]['classname'](self::$types[$type]['config']);
		else
			return null;
	}
}

class Pitbull_Cache extends Pitbull_DynamicFactory { }
	
?>
