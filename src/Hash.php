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
class Hash {
	private $hash;
	private $silent;
	private $callback;

	/**
	 * @var Hash
	 */
	private static $instance;

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

	/**
	 * @return Hash
	 */
	public static function getInstance() {

		// Check if instance is already exists
		if(self::$instance == null) {
			self::$instance = new Hash();
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

	/**
	 * Sets the callback. This feature is run every time an ajax request is handled.
	 *
	 * A callback is *always* produced, either by:
	 *
	 * 1. Explicitly sending a callback variable
	 * 2. The entire hash array string
	 * 3. A specific string sent to this method.
	 *
	 * The callbacks will be produced in this order.
	 *
	 * @param      $a
	 *
	 * @param bool $urlencode If set to TRUE, will urlencode the callback
	 *
	 * @return bool
	 */
	function setCallback($a, $urlencode = TRUE){
		# No value sent
		if(!$a){
			return true;
		}

		# If an array is sent
		if(is_array($a)){

			if($this->callback = $a['vars']['callback']){
				//If the array already have a specified callback key/value
				return true;
			} else {
				//Create a callback from the complete AJAX request
				$this->callback = str::generate_uri($a, true);
			}

			if($urlencode){
				$this->callback = str::urlencode($this->callback);
			}

			return true;
		}

		# If a string or an int is sent
		if(is_string($a) || is_numeric($a)){
			$this->callback = $urlencode ? str::urlencode($a) : $a;
		}

		return true;
	}

	/**
	 * The appropriate way of getting the callback.
	 * Will by default urlDEcode the callback.
	 * This is useful for when the callback is going into the $this->hash->set(); method.
	 *
	 * @param bool $urlencode If set to TRUE, the callback will be urlENcoded
	 *                        so that it can be stored as a variable.
	 *
	 * @return string
	 */
	function getCallback($urlencode = NULL){
		if($urlencode){
			return str::urlencode($this->callback);
		} else {
			return urldecode($this->callback);
		}
	}
}
