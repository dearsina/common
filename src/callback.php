<?php
namespace App\Common;


/**
 * Class callback
 * Quasi-global, static callback that can be called from anywhere.
 *
 * Not sure if we need this.
 *
 * <code>
 * $this->callback->set("hash");
 * </code>
 *
 * @package App\Common
 */
class callback {
	private $callback;
	private static $instance = null;

	private function __construct() {
		//The constructor is private so that the class can be run in static mode.
		$this->callback = false;
	}

	private function __clone() {
		//Stops the cloning of this object.
	}

	private function __wakeup() {
		//Stops the unserialising of this object.
	}

	public static function getInstance() {
		if(self::$instance == null) {
			//If this is the first instance request
			self::$instance = new callback();
		}
		return self::$instance;
	}

//	/**
//	 * Generates the hash per request for use when logging in mid-process.
//	 *
//	 * The $this->callback variable will ALWAYS be encoded, and will need to be
//	 * decoded for use.
//	 *
//	 * @param $a array|string
//	 *
//	 * @return bool Always returns TRUE, after having stored the $this->callback variable
//	 */
//	function set($a){
//		# When callback is used in forms, the callback is sent as a string
//		if(!$a || is_string($a) || is_numeric($a)){
//			$this->callback = str::urlencode($a);
//			return true;
//		}
//
//		# Otherwise, the callback is created from the AJAX request formed by the change in the hash (url)
//		$this->callback = str::generate_uri($a, true);
//
//		return $this->callback;
//	}

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
	function set($a, $urlencode = TRUE){
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
	function get($urlencode = NULL){
		if($urlencode){
			return str::urlencode($this->callback);
		} else {
			return urldecode($this->callback);
		}
	}
}
