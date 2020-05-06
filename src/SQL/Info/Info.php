<?php


namespace App\Common\SQL\Info;

use App\Common\SQL\Factory;
use App\Common\str;

class Info {
	/**
	 * The Info() instance.
	 *
	 * @var Info
	 */
	private static $instance;

	/**
	 * @var
	 */
	protected $info;

	/**
	 * @var mySQL\mySQL
	 */
	protected static $sql;

	/**
	 * The constructor is private so that the class
	 * can be run in static mode.
	 *
	 * Cloning and wakeup are also set to private to prevent
	 * cloning and unserialising of the Info() object.
	 */
	private function __construct() {
		$this->sql = Factory::getInstance();
	}
	private function __clone() {}
	private function __wakeup() {}

	/**
	 * Checks to see if an instance of Info()
	 * already exists, if not creates it,
	 * and returns it.
	 *
	 * @return Info
	 */
	public static function getInstance() {
		if(!self::$instance) {
			self::$instance = new Info();
		}
		return self::$instance;
	}

	public function getInfo($a, $rel_id = NULL){
		if(is_string($a)){
			$a = [
				"rel_table" => $a,
				"rel_id" => $rel_id
			];
		}
		if(!is_array($a)){
			throw new \Exception("Only arrays or strings can be fed to the first value of the <code>info</code> method.");
		}
		
		# Clean up the request variables
		$a = $this->cleanVars($a);
		
		# Get cached results if they exist
		if($cached_results = $this->getCachedResults($a)){
			return $cached_results;
		}

		if(!$class_path = str::findClass("Info", $a['rel_table'])){
			throw new \Exception("The <code>{$a['rel_table']}\\Info</code> method doesn't exists. Use `\$this->sql->select()` instead.");
		}

		# Load up an instance of the custom info class
		$class_instance = new $class_path();

		# Prepare the variables
		$class_instance->prepare($a);

		# Run the SQL query
		if (!$rows = $this->sql->select($a)) {
			return false;
		}

		# Go thru each row
		foreach($rows as $id => $row){
			# Format the data (optional)
			$class_instance->format($row);
			$rows[$id] = $row;
		}

		# Store the cached results
		$this->setCachedResults($a, $rows);

		# If a particular ID has been request, only return that one row, otherwise all
		return $a['rel_id'] ? reset($rows) : $rows;
	}

	private function cleanVars($a){
		if($a['table']){
			$a['rel_table'] = $a['table'];
		} else if ($a['rel_table']){
			$a['table'] = $a['rel_table'];
		}
		# We need to force a numerical array result even if only one row is expected
		if($a['rel_id']){
			$a['where']["{$a['table']}_id"] = $a['where']["{$a['table']}_id"] ?: $a['rel_id'];
		}
		return $a;
	}

	/**
	 * Clears the cache of stored SQL requests.
	 * Useful for long cron jobs.
	 * 
	 * @return bool
	 */
	public function clearCache(){
		$this->info = [];
		return true;
	}

	private function getCachedResults($a){
		return $this->info[$this->fingerprint($a)];
	}

	/**
	 * Store the information requested results, so that if future requests
	 * are for the same data, the cached results are returned instead.
	 *
	 * @param $request
	 *
	 * @return mixed
	 */
	public function setCachedResults($request, $results){
		if($request['rel_id'] && str::isNumericArray($results)){
			//if a rel_id is requested, only store the first (and only) value
			//it also checks more than one result was in fact returned
			$this->info[$this->fingerprint($request)] = reset($results);
		} else {
			$this->info[$this->fingerprint($request)] = $results;
		}

		return true;
	}

	/**
	 * Creates a fingerprint from the array of meta data about the information required.
	 * The finger print is used to identify if the exact same request has been
	 * processed already as part of the same AJAX call. In which case, the cached
	 * value will be used instead.
	 *
	 * @param $a
	 *
	 * @return string
	 */
	public function fingerprint($a){
		return md5(json_encode($a));
	}
}