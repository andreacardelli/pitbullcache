<?php
// Classi per varie tipologie di cache con Dynamic Factories

abstract class Pitbull_Base_Cache
{
	abstract function __construct($config);
        abstract function fetch($key);
        abstract function store($key,$data,$ttl);
        abstract function delete($key);
        abstract function cleanUpExpired($output);
        function sanitizeKey($key) {
   			$pattern = '/[^:a-zA-Z0-9_-]/';
			return preg_replace($pattern, '_', (string) $key);
		}
}

class Pitbull_Filesystem_Cache extends Pitbull_Base_Cache
{

	function __construct($config) {
		$this->path = $config;
		if (!is_dir($this->path)) {
		    if (!mkdir($this->path, 0777, true)) throw new Exception("Could not create cache directory: $this->path");
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

	function cleanUpExpired($output='array' ) { // otherwise output='json'
		// loop on all files and delete expired
		// find all cache file in $this->path
		$files = glob($this->path.'/*');
		$totalfiles = $deleted= 0;
		foreach($files as $filename) {
		    $totalfiles++;
		    //foreach get the content
			$data = file_get_contents($filename);
			//unserializza i dati
			$data = @unserialize($data);
			//check if still valid otherwise delete
			if (time() > $data[0]) {
				// Unlinking when the file was expired
				unlink($filename);
				$deleted++;
			}
		}
		$return = array('total_files'=>($totalfiles-$deleted),'deleted_files'=>$deleted);
		return ($output=='array')?$return:json_encode($return);
	}

  	private function getFileName($key) {
      	return $this->path . $this->sanitizeKey($key);
  	}
}

class Pitbull_Redis_Cache extends Pitbull_Base_Cache
{
		function __construct ($config) {
			if (!class_exists("Predis\Client",true)) throw new Exception('nrk/predis Library is not available');
			// else do
			$this->cache = new Predis\Client($config);
		}
		function fetch($key) { 
			$key = $this->sanitizeKey($key);
			return json_decode($this->cache->get($key),true);;
		}
        function store($key,$data,$ttl) { 
        	$key = $this->sanitizeKey($key);
        	$this->cache->set($key, json_encode($data));
        	$this->cache->expire($key,$ttl);
        	return $key;
        }
        function delete($key) {
        	$key = $this->sanitizeKey($key);
        	return $this->cache->del($key);
    	}
    	function cleanUpExpired($output='array' ) { // otherwise output='json'
			// cleanup is done automatically so no need
    		// just come back number of entries
    		$return = array('total_files'=>$this->cache->dbSize(),'deleted_files'=>0);
			return ($output=='array')?$return:json_encode($return);
		}
}

class Pitbull_S3_Cache extends Pitbull_Base_Cache
{

	function __construct($config) {
		if (!class_exists("S3",true)) throw new Exception('tpyo/amazon-s3-php-class Library is not available');
		// else do
		$this->cache = new S3($config['key'], $config['secret']);
		$this->bucket = $config['bucket'];
	}
	 // This is the function you store information with
	function store($key,$data,$ttl) {

		// Serializing along with the TTL
		$data = serialize(array(time()+$ttl,$data));

		// Save file to S3
		$h = $this->cache->putObject($data,$this->bucket,$this->getFileName($key));
		if (!$h) throw new Exception('Could not write to S3'); 
	}

  	// The function to fetch data returns false on failure
	function fetch($key) {
		$filename = $this->getFileName($key);
		$data = $this->cache->getObject($this->bucket, $filename);
		$data=$data->body;
		if (!$data) return false;

		$data = @unserialize($data);
		if (!$data) {
		 // If unserializing somehow didn't work out, we'll delete the file
		 $this->cache->deleteObject($this->bucket, $filename);
		 return false;
		}

		if (time() > $data[0]) {
		 // Unlinking when the file was expired
		 $this->cache->deleteObject($this->bucket, $filename);
		 return false;
		}
		return $data[1];
	}

	function delete( $key ) {
		$filename = $this->getFileName($key);
		if (!$this->cache->deleteObject($this->bucket, $filename)) {
		  return false;
		} 
		return true;
	}

	function cleanUpExpired($output='array') { // otherwise output='json'
		// loop on all files and delete expired
		// find all cache file in $this->bucket
		$files = $this->cache->getBucket($this->bucket,'pitbullcache');
		$totalfiles = $deleted= 0;
		foreach($files as $filename=>$value) {
			$totalfiles++;
		    //foreach get the content
			$data = file_get_contents($filename);
			//unserializza content
			$data = @unserialize($data);
			//check if still valid otherwise delete
			if (time() > $data[0]) {
				// Unlinking when the file was expired
				$this->cache->deleteObject($this->bucket, $filename);
				$deleted++;
			}
		}
		$return = array('total_files'=>($totalfiles-$deleted),'deleted_files'=>$deleted);
		return ($output=='array')?$return:json_encode($return);
	}

  	private function getFileName($key) {
      	return 'pitbullcache/' . $this->sanitizeKey($key);
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
		if (isset(self::$types[$type]['classname']) && class_exists(self::$types[$type]['classname']))
			return new self::$types[$type]['classname'](self::$types[$type]['config']);
		else
			echo "PitBull_Cache: The type '$type' is not registered or defined";
			die();
	}
}

class Pitbull_Cache extends Pitbull_DynamicFactory { }
	
?>
