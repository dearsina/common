<?php


namespace App\Common;

use App\Common\SQL\Factory;
use App\Common\SQL\mySQL\mySQL;

/**
 * Class PA
 * @package App\Common
 */
class PA {
	/**
	 * @var log
	 */
	private $log;

	/**
	 * @var mySQL
	 */
	private $sql;

	private static $instance = null;
	/**
	 * The constructor is private so that the class can be run in static mode
	 *
	 */
	private function __construct () {
		# Set up an internal log
		$this->log = Log::getInstance();
		$this->sql = Factory::getInstance();
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

//	/**
//	 * Append a recipient of the msg.
//	 *
//	 * @param array|string $a If a string is passed,
//	 *                        it's assumed to be an id,
//	 *                        if it's an array,
//	 *                        it's assumed to be a hash.
//	 */
//	public function to($a){
//		if(is_array($a)){
//
//		} else {
//
//		}
//	}
//
//	/**
//	 * @param $a
//	 */
//	public function msg($a){
//
//	}
//
//	/**
//	 * @param $a
//	 */
//	public function listen($a){
//
//	}

	/**
	 * @param null $a
	 *
	 * @return bool
	 */
	public function speak($a = NULL){
		if(!$recipients = $this->getRecipients($a)){
			$this->log->warning("No recipients found for the broadcast.");
			return false;
		}

		try {
			$this->push($recipients, $a['data']);
		}
		catch (\Exception $e){
			$this->log->error($e);
			return false;
		}
		//

		return true;
	}

	/**
	 * Given the array from speak,
	 * builds a list of recipients.
	 * If no recipients are found,
	 * returns bool FALSE.
	 *
	 * @param array $a
	 *
	 * @return array|bool
	 */
	private function getRecipients(array $a)
	{
		# If a list of recipients was explicitly sent
		if(is_array($a['fd'])){
			return $a['fd'];
		}
		//TODO decide between "fd" and "recipients"

		# If a list of recipients was explicitly sent
		if(is_array($a['recipients'])){
			return $a['recipients'];
		}

		# Assume we have to generate recipients

		# The where clause is how we identify recipients
		$where = ["closed" => NULL];

		# User permissions based recipients
		if($a['rel_table']){
			//if all active users who have permissions over a particular rel_table/id should get the message
			$join[] = [
				"table" => "user_permission",
				"on" => "user_id",
				"where" => [
					"rel_table" => $a['rel_table'],
					"rel_id" => $a['rel_id'],
					"r" => true
					// The user needs to at least have read access
				]
			];
		}

		# Role based recipients
		else if($a['role']){
			//if all logged on users of a particular role should get the message
			$join[] = [
				"table" => "user_role",
				"on" => "user_id",
				"where" => [
					"rel_table" => $a['role']
				]
			];
		}

		else {
			//if no recipients have been identified,
			//assume only the person kicking off the
			//ajax is the only person to be notified
			$where["session_id"] = session_id();
		}

		if(!$connections = $this->sql->select([
			"table" => "connection",
			"join" => $join,
			"where" => $where
		])){
			//if no connections are found
			return false;
		}

		foreach($connections as $connection){
			$recipients[] = $connection['fd'];
		}

		return $recipients;
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