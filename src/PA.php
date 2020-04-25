<?php


namespace App\Common;

use App\Common\log;
use App\Common\SQL\mySQL;

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
		$this->log = Log::getInstance();
		$this->sql = mySQL::getInstance();
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

	/**
	 * @param $a
	 */
	public function msg($a){

	}

	/**
	 * @param $a
	 */
	public function listen($a){

	}

	public function speak($a = NULL){
		if(!$a['fd']){
			//if no recipients have been identified,
			//assume only the person kicking off the
			//ajax is to be notified
			if($recipients = $this->sql->select([
				"table" => "connection",
				"where" => [
					"closed" => "NULL",
					"session_id" => session_id()
				]
			])){
				foreach($recipients as $recipient){
					$a['fd'][] = $recipient['fd'];
				}
			}
		}

		try {
			$this->push($a['fd'], $a['data']);
		}
		catch (\Exception $e){
			$this->log->error($e);
			return false;
		}

		return true;
	}

	/**
	 * Push a message to a given list of recipients.
	 *
	 * @param array $fd A numerical array of recipients (`fd` numbers)
	 * @param array $message An array of messages, direction, instructions.
	 *
	 * @return bool TRUE on success, exceptions on error.
	 * @throws \Exception
	 */
	private function push(array $fd, array $message){
		if(empty($fd)){
			throw new \Exception("No recipients provided.");
		}
		if(empty($message)){
			throw new \Exception("No message provided.");
		}

		# Prepare the data as a single commandline friendly json string
		$data = urlencode(json_encode([
			"fd" => $fd,
			"data" => $message
		]));

		$cmd  = "'go(function(){";
		$cmd .= "\$client = new \\Swoole\\Coroutine\\Http\\Client(\"{$_ENV['websocket_internal_ip']}\", \"{$_ENV['websocket_internal_port']}\");";
		$cmd .= "\$client->upgrade(\"/\");";
		$cmd .= "\$client->push(urldecode(\"{$data}\"));";
		$cmd .= "\$client->close();";
		$cmd .= "});'";

		# Execute the command
		$output = shell_exec("php -r {$cmd} 2>&1");

		if($output){
			throw new \Exception($output);
		}

		return true;
	}
}