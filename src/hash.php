<?php
namespace App\Common;


/**
 * Class hash
 * Quasi-global, static hash that can be called from anywhere.
 *
 * <code>
 * $this->hash->set("hash");
 * </code>
 *
 * @package App\Common
 */
class hash {
	private $hash;
	private $silent;
	private static $instance = null;

	/**
	 * The constructor is private so that the class can be run in static mode
	 *
	 */
	private function __construct() {
		$this->hash = false;
	}

	private function __clone() {
		// Stopping Clonning of Object
	}

	private function __wakeup() {
		// Stopping unserialize of object
	}

	public static function getInstance() {

		// Check if instance is already exists
		if(self::$instance == null) {
			self::$instance = new hash();
		}

		return self::$instance;
	}

	/**
	 * Set the hash
	 * If a hash is set, the browser will be re-directed to the new hash.
	 * The method will override existing hashes set.
	 * <code>
	 * $this->hash->set("rel_table/rel_id/action");
	 * </code>
	 *
	 * @param @a string|array Do not prefix the hash with a hashtag, it will be added later automatically.
	 *
	 * @return bool
	 */
	function set($a){
		if($a === false){
			return $this->unset();
		} else if(is_array($a)){
			$this->hash = str::generate_uri($a);
		} else if(is_int($a) || is_string($a)){
			$this->hash = urldecode($a);
		} else {
			return false;
		}
		return true;
	}

	function unset(){
		$this->hash = false;
		return true;
	}

	function get(){
		return $this->hash;
	}

	/**
	 * If called (without a variable),
	 * or if the varible is set to TRUE,
	 * the browser will change the hash,
	 * without refreshing the screen.
	 *
	 * @param bool $silent
	 */
	function silent($silent = TRUE){
		$this->silent = $silent;
	}

	function get_silent(){
		return $this->silent;
	}
}
