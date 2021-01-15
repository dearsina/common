<?php


namespace App\Common;

use App\Common\SQL\Factory;
use App\Common\SQL\mySQL\mySQL;
use Swoole\Coroutine\Http\Client;

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

	/**
	 * Both join and where are used to build
	 * a query to identify potential recipients
	 * of immediate alerts.
	 *
	 * @var array
	 */
	private array $join = [];
	private array $where = [];

	private static $instance = null;
	/**
	 * The constructor is private so that the class can be run in static mode
	 *
	 */
	private function __construct () {
		# Set up an internal alert
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

	/**
	 * Sends data to the requested list of recipients.
	 *
	 * Currently set so that if (for some reason) a message should
	 * go to _all_ connections, the recipients list must explicitly
	 * be set to an (empty) array, making it a little harder to
	 * accidentally send a message to all.
	 * ABOVE DISABLED TO AVOID MASS MESSAGING
	 *
	 * @param array $recipients
	 * @param array $data
	 *
	 * @return bool
	 */
	public function speak(array $recipients, array $data): bool
	{
		# Get connection FDs from the recipient criteria
		if(!$fds = $this->getConnectionFDs($recipients)){
			//if no currently open connections fit the criteria
			return false;
			//Don't push any messages on the network
			//TODO Log messages that weren't sent
		}

		try {
			$this->push($fds, $data);
		}
		catch (\Exception $e){
			$this->log->error($e);
			return false;
		}

		return true;
	}

	/**
	 * Sends a message to the _current_ user (only).
	 *
	 * Useful if messages are to be funnelled back
	 * mid-process.
	 *
	 * @param array $output The output from $this->output->get() or other array of messages to send to the user.
	 */
	public function asyncSpeak(array $output): void
	{
		# See if there is global data about the requester
		global $user_id;
		global $session_id;

		# Ensure there is a recipient
		if(!$user_id && !$session_id){
			//if no requester data is found
			return;
			//Pencils down
		}

		# Send the output to the requester if found
		$recipients = [
			"user_id" => $user_id,
			"session_id" => $session_id
		];

		# Force to true
		$output['success'] = true;
		/**
		 * The reason why success is forced to true,
		 * even if scenarios where its not, is that
		 * any success=false that reaches JS, will
		 * automatically prompt the URL to silently
		 * be reverted back one step. This could have
		 * unintended consequences.
		 *
		 * Asynchronous requests should not
		 * be able to dictate history steps because
		 * the request is not aware of where the user
		 * is at the time of output delivery.
		 *
		 * Asynchronous requests can, but only under
		 * careful considerations, force a hash change
		 * and direct the user to a particular page.
		 *
		 */

		# Send the output to their screen
		$this->speak($recipients, $output);
	}

	/**
	 * Given the array from speak,
	 * builds a list of recipient
	 * connection FDs.
	 * If no recipients are found,
	 * returns bool FALSE.
	 *
	 * @param array $a
	 *
	 * @return array|bool
	 */
	private function getConnectionFDs(array $a)
	{
		# If a list of recipient FDs was explicitly sent
		if($a['fd']){echo $i++;
			return is_array($a['fd']) ? $a['fd'] : [$a['fd']];
		}

		# If we have to generate recipients
		$this->join = [];
		//Reset the class variables

		$this->where = [
			# We're only interested in currently open connections
			"closed" => NULL,
			# And where there is an FD number
			["fd", "IS NOT", NULL]
		];

		# User permissions based recipients
		$this->getRecipientsBasedOnUserPermissions($a);

		# Role based recipients
		$this->getRecipientsBasedOnRolePermissions($a);

		# Requester based recipient (there is only one requester at any time)
		$this->getRecipientsBasedOnRequester($a);

		/**
		 * While the recipient criteria can be combined,
		 * they will only further limit each other.
		 */
		if(!$connections = $this->sql->select([
			"distinct" => true,
			"columns" => [
				"fd",
			],
			"table" => "connection",
			"join" => $this->join,
			"where" => $this->where
		])){
			//if no connections are found
			return false;
		}

		# Return a simple array of fd's
		return array_column($connections, "fd");
	}

	/**
	 * If a `rel_table` is included, will send the message to any
	 * user who has permissions to READ that `rel_table`.
	 * If a `rel_id` is included, will limit it to those with
	 * permissions for the `rel_table` + `rel_id` *combined*.
	 *
	 * @param $a
	 */
	private function getRecipientsBasedOnUserPermissions($a): void
	{
		extract($a);

		if(!$rel_table){
			return;
		}

		$this->join[] = [
			"columns" => false,
			"table" => "user_permission",
			"on" => "user_id",
			"where" => [
				"rel_table" => $rel_table,
				"rel_id" => $rel_id ?: false,
				"r" => true
				// The user needs to at least have read access
			]
		];
	}

	/**
	 * If a particular `role` is included, will send the message to
	 * any user who is currently logged in as that role.
	 *
	 * @param $a
	 */
	private function getRecipientsBasedOnRolePermissions($a): void
	{
		extract($a);

		if(!$role){
			return;
		}

		$this->join[] = [
			"columns" => false,
			"table" => "user_role",
			"on" => "user_id",
			"where" => [
				"rel_table" => $role
			]
		];
	}

	/**
	 * Requester data can be sent as user_id and session_id variables.
	 *
	 * @param $a
	 */
	private function getRecipientsBasedOnRequester($a): void
	{
		extract($a);

		if($user_id){
			$this->where['user_id'] = $user_id;
		} else if($session_id){
			$this->where['session_id'] = $session_id;
		}
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

		if(str::runFromCLI()){
			//If this method is called from the CLI

			/**
			 * The below needs to be in the go() function because Swoole said so
			 * @link https://www.qinziheng.com/swoole/7477.htm
			 */
			go(function() use($fd, $message){
				$client = new Client($_ENV['websocket_internal_ip'], $_ENV['websocket_internal_port']);
				$client->upgrade("/");
				$client->push(json_encode([
					"fd" => $fd,
					"data" => $message
				]));
				$client->close();
			});

			return true;
		}

		# If this method is NOT called from CLI, prepare the data as a single commandline friendly json string
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

		# TODO Figure out a fast way to send messages that are too long shell_exec php messages cannot be in the kbs

		# Execute the command
		$output = shell_exec("php -r {$cmd} 2>&1");

		if($output){
			throw new \Exception($output);
		}

		return true;
	}
}