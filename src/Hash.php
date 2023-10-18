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
	/**
	 * If set to true, will silently change the
	 * user's URL.
	 *
	 * @var bool
	 */
	private $silent;

	/**
	 * Contains the next (or previous) URL to go to.
	 * @var string
	 */
	private $callback;

	private $hash;

	/**
	 * The constructor is private so that the class
	 * can be run in static mode.
	 *
	 * Cloning and wakeup are also set to private to prevent
	 * cloning and unserialising of the Hash() object.
	 */
	private function __construct()
	{

	}

	private function __clone()
	{
	}

	private function __wakeup()
	{
	}

	/**
	 * Checks to see if an instance of Hash()
	 * already exists, if not creates it,
	 * and returns it.
	 *
	 * @return Hash
	 */
	final public static function getInstance(): Hash
	{
		static $instance = NULL;
		if(!$instance){
			$instance = new Hash();
		}
		return $instance;
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
	function set($a)
	{
		if($a === false){
			return $this->unset();
		}
		else if(is_array($a)){
			$this->hash = str::generate_uri($a);
		}
		else if(is_int($a)){
			$this->hash = $a;
		}
		else if(is_string($a)){
			$this->hash = urldecode($a);
		}
		else {
			return false;
		}
		return true;
	}

	public function unset()
	{
		$this->hash = false;
		return true;
	}

	/**
	 * The appropriate way of getting the current hash.
	 *
	 * @param bool $urlencode If set to TRUE, the callback will be urlENcoded
	 *                        so that it can be stored as a variable.
	 *
	 * @return string
	 */
	function get($urlencode = NULL)
	{
		if($urlencode){
			return str::urlencode($this->hash);
		}
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
	function silent($silent = true): void
	{
		$this->silent = $silent;
	}

	/**
	 * @return bool
	 */
	function getSilent()
	{
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
	 * @param array|string|bool|null $a
	 *
	 * @param bool                   $urlencode If set to TRUE, will urlencode the callback
	 *
	 * @return void
	 */
	function setCallback($a, $urlencode = true): void
	{
		# No value sent
		if(!$a === NULL){
			return;
		}

		# If an array is sent
		if(is_array($a)){

			if($this->callback = $a['vars']['callback']){
				//If the array already have a specified callback key/value
				return;
			}

			else {
				//Create a callback from the complete AJAX request
				$this->callback = str::generate_uri($a, true);
			}

			if($urlencode){
				$this->callback = str::urlencode($this->callback);
			}

			return;
		}

		# For everything else
		$this->callback = $urlencode ? str::urlencode($a) : $a;

		return;
	}

	/**
	 * The appropriate way of getting the callback.
	 * Will by default urlDEcode the callback.
	 * This is useful for when the callback is going into the $this->hash->set(); method.
	 *
	 * @param bool|null $urlencode If set to TRUE, the callback will be urlENcoded
	 *                             so that it can be stored as a variable.
	 *
	 * @return string
	 */
	function getCallback(?bool $urlencode = NULL): ?string
	{

		# Remove the first slash (not sure about the consequences)
		if($this->callback !== null && substr(urldecode($this->callback), 0, 1) == '/'){
			$this->callback = substr(urldecode($this->callback), 1);
		}

		if($urlencode){
			return str::urlencode($this->callback);
		}
		else {
			return urldecode($this->callback ? $this->callback : '');
		}
	}
}
