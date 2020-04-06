<?php


namespace App\Common;

use App\Common\log;

class PA {
	/**
	 * @var log
	 */
	private $log;

	private static $instance = null;
	/**
	 * The constructor is private so that the class can be run in static mode
	 *
	 */
	private function __construct () {
		# Set up an internal log
		$this->log = log::getInstance();
	}

	/**
	 * Stopps cloning of the object
	 */
	private function __clone() {}

	/**
	 * Stops unserializing of the object
	 */
	private function __wakeup() {}

	/**
	 * Is the instance to call to initiate the
	 * connection to the Websocket server.
	 * <code>
	 * $this->pa = PA::getInstance();
	 * </code>
	 * @return PA
	 */
	public static function getInstance() {
		# Check if instance is already exists
		if(self::$instance == null) {
			self::$instance = new PA();
		}
		return self::$instance;
	}

	/**
	 * Append a recipient of the msg.
	 *
	 * @param array|string $a If a string is passed,
	 *                        it's assumed to be an id,
	 *                        if it's an array,
	 *                        it's assumed to be a hash.
	 */
	public function to($a){
		if(is_array($a)){

		} else {

		}
	}

	public function msg($a){

	}

	public function listen($a){

	}

	public function speak($a = NULL){

		$array = [
			"command" => "update_data",
			"user" => "tester01"
		];
		$data = urlencode(json_encode($array));

		$cmd  = "'go(function(){";
		$cmd .= "\$client = new \\Swoole\\Coroutine\\Http\\Client(\"{$_ENV['websocket_ip']}\", {$_ENV['websocket_port']});";
		$cmd .= "\$client->upgrade(\"/\");";
		$cmd .= "\$client->push(urldecode(\"{$data}\"));";
		$cmd .= "\$client->close();";
		$cmd .= "});'";

		$output = shell_exec("php -r {$cmd} 2>&1");
		echo $output;
	}
}