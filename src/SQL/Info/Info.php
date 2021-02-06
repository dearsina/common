<?php


namespace App\Common\SQL\Info;

use App\Common\SQL\Factory;
use App\Common\SQL\mySQL\mySQL;
use App\Common\str;

/**
 * Class Info
 * @package App\Common\SQL\Info
 */
class Info {
	/**
	 * The Info() instance.
	 *
	 * @var Info
	 */
	private static $instance;

	/**
	 * Contains all cached results.
	 *
	 * @var array
	 */
	protected $info = [];

	/**
	 * @var mySQL
	 */
	protected $sql;

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

    /**
     * @param             $a
     * @param string|null $rel_id
     * @param bool|null $refresh If set to TRUE, will ignore any cached results.
     *
     * @return array|null
     * @throws \Exception
     */
	public function getInfo($a, ?string $rel_id = NULL, ?bool $refresh = NULL): ?array
	{
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

		if(!$refresh){
			//If force refresh is not set

			# Get cached results if they exist
			if(($cached_results = $this->getCachedResults($a)) !== FALSE){
				return $cached_results;
			}
		}

		# Run either a custom or generic process to get the rows
		if($class_path = str::findClass("Info", $a['rel_table'])){
			$rows = $this->customProcess($class_path, $a);
		} else {
			$rows = $this->genericProcess($a);
		}

		# Store the cached results
		$this->setCachedResults($a, $rows);

		# If there aren't any actual results
		if(!$rows){
			return NULL;
		}

		/**
		 * As rel_id and limit=1 are stripped out to always
		 * return a numerical array, if either were
		 * part of the original request, re-instate the limits
		 * by only returning the first (and most probably only)
		 * result.
		 */
		if(($a['limit'] == 1) || $a['rel_id']){
			return reset($rows);
		}


		return $rows;
	}

	/**
	 * If there is no custom process, just run it thru select.
	 * Why you ask?
	 * The benefit of using $this->info() is caching (which
	 * could be a double edged sword if results change),
	 * and ability to write uniform code even when rel_table is not known.
	 *
	 * @param array $a
	 *
	 * @return array|bool|mixed|string
	 */
	private function genericProcess(array $a)
	{
		# Set a generic order by
		$a['order_by'] = [
			"order" => "ASC",
			"title" => "ASC"
		];

		# Run the SQL query
		if (!$rows = $this->sql->select($a)) {
			return false;
		}

		# Ensure rows array is numerical
		$this->ensureRowsAreNumerical($a, $rows);

		return $rows;
	}

	/**
	 * If only one row was requested, and
	 * the select() method returned an associative
	 * array containing that one row, return
	 * a numeric array to ensure consistency
	 * with the custom methods.
	 *
	 * The one row limit will be imposed before
	 * the row is sent to the user.
	 *
	 * @param array $a
	 * @param array $rows
	 */
	private function ensureRowsAreNumerical(array $a, array &$rows): void
	{
		if($a['limit'] == 1){
			$rows = [$rows];
		}
	}

	/**
	 * If the rel_table has a custom Info() class, use it.
	 *
	 * @param string $class_path
	 * @param array  $a
	 *
	 * @return array|bool|mixed|string
	 */
	private function customProcess(string $class_path, array $a)
	{
		# Prepare the variables
		$class_path::prepare($a);
//		if($a['table'] == "entity"){echo json_encode($a);exit;}

		# Run the SQL query
		if (!$rows = $this->sql->select($a)) {
			return false;
		}

		# Ensure rows array is numerical
		$this->ensureRowsAreNumerical($a, $rows);

		# Go thru each row
		foreach($rows as $id => $row){
			# Format the data (optional)
			$class_path::format($row);
			$rows[$id] = $row;
		}

		return $rows;
	}

	/**
	 * @param $a
	 *
	 * @return mixed
	 */
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

	/**
	 * Will return cached results if they exist.
	 * A valid cached result could also be NULL.
	 *
	 * Will return FALSE if no cache exists.
	 *
	 * @param $a
	 *
	 * @return mixed
	 */
	private function getCachedResults($a){
		$id = $this->fingerprint($a);
		if(!key_exists($id, $this->info)){
			return false;
		}
		return $this->info[$id];
	}

	/**
	 * Store the information requested results, so that if future requests
	 * are for the same data, the cached results are returned instead.
	 *
	 * @param $request
	 *
	 * @param $results
	 *
	 * @return mixed
	 */
	public function setCachedResults($request, $results){
		if(($request['limit'] == 1 || $request['rel_id']) && str::isNumericArray($results)){
			//if a rel_id is requested or limit =1 , only store the first (and only) value
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