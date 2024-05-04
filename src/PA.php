<?php


namespace App\Common;

use App\Common\ConnectionMessage\ConnectionMessage;
use App\Common\SQL\Factory;
use App\Common\SQL\Info\Info;
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

	/**
	 * @var Info
	 */
	private $info;

	/**
	 * Both join and where are used to build
	 * a query to identify potential recipients
	 * of immediate alerts.
	 *
	 * @var array
	 */
	private array $join = [];
	private array $where = [];

	private static $instance = NULL;

	/**
	 * The constructor is private so that the class can be run in static mode
	 *
	 */
	private function __construct()
	{
		# Set up an internal alert
		$this->log = Log::getInstance();
		$this->sql = Factory::getInstance();
	}

	/**
	 * Shortcut for complex, repeated SQL queries. Simple syntax:
	 * <code>
	 * $rel = $this->info($rel_table, $rel_id, $refresh);
	 * </code>
	 * alternatively, add more colour:
	 * <code>
	 * $rel = $this->info([
	 *    "rel_table" => $rel_table,
	 *    "rel_id" => $rel_id,
	 *    "where" => [
	 *        "key" => "val"
	 *    ]
	 * ], NULL, $refresh, ["joins"]);
	 * </code>
	 *
	 * @param             $rel_table_or_array
	 * @param string|null $rel_id
	 * @param null        $refresh
	 * @param array|null  $joins
	 *
	 * @return array|null
	 * @throws Exception
	 */
	protected function info($rel_table_or_array, ?string $rel_id = NULL, $refresh = NULL, ?array $joins = NULL): ?array
	{
		if(!$this->info){
			$this->info = Info::getInstance();
		}
		return $this->info->getInfo($rel_table_or_array, $rel_id, (bool)$refresh, $joins);
	}

	/**
	 * Stopps cloning of the object
	 */
	private function __clone()
	{
	}

	/**
	 * Stops unserializing of the object
	 */
	private function __wakeup()
	{
	}

	/**
	 * Is the instance to call to initiate the
	 * connection to the Websocket server.
	 * <code>
	 * $this->pa = PA::getInstance();
	 * </code>
	 * @return PA
	 */
	public static function getInstance()
	{
		# Check if instance is already exists
		if(self::$instance == NULL){
			self::$instance = new PA();
		}
		return self::$instance;
	}

	/**
	 * Sends data to the requested list of recipients.
	 * If the recipient doesn't have a WebSockets recipient
	 * address (an FD number), the data is stored awaiting
	 * a pull request from the recipient's browser.
	 *
	 * @param array $recipients
	 * @param array $data
	 *
	 * @return bool
	 */
	public function speak(array $recipients, array $data): bool
	{
		if($connections = $this->getConnections($recipients)){
			# For each connection found, determine whether it's a push or a pull connection
			foreach($connections as $connection){
				# Push (WebSockets)
				if($connection['fd']){
					//If a connection has an FD (WebSocket recipient address)

					# Add them to the list of recipients for the push message
					$fds[] = $connection['fd'];
				}

				# Pull (Data is stored, awaiting pull from browser)
				else {
					//If the connection exists, but has no websocket recipient

					# Store the data for pull request downloads
					ConnectionMessage::store($connection['connection_id'], $data);
				}
			}
		}

		if(!$fds){
			//if no currently open connections fit the criteria
			return false;
			//Don't push any messages on the network
		}

		try {
			$this->push($fds, $data);
		}
		catch(\Exception $e) {
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
		if($session_id){
			$recipients['session_id'] = $session_id;
		}

		else if($user_id){
			$recipients['user_id'] = $user_id;
		}

		else {
			return;
		}
		/**
		 * Session ID takes precedence over user ID because
		 * in the event that both are passed, the
		 * session ID is a more reliable way to identify
		 * the user, especially during the login process.
		 */

		# Force to true
		$output['success'] = true;
		/**
		 * The reason why success is forced to true,
		 * even if scenarios where it's not, is that
		 * any success=false that reaches JS, will
		 * automatically prompt the URL to silently
		 * be reverted one step. This could have
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
	private function getConnections(array $a): ?array
	{
		# Failsafe in case someone sends an empty array (but with valid keys)
		foreach($a as $key => $val){
			if(!strlen($val)){
				unset($a[$key]);
			}
		}
		if(!$a){
			return NULL;
		}


		extract($a);

		# If a list of recipient FDs was explicitly sent
		if($a['fd']){
			return is_array($a['fd']) ? $a['fd'] : [$a['fd']];
		}

		# We're only interested in connections on the current server
		$server = $this->sql->select([
			"columns" => "server_id",
			"table" => "connection",
			"where" => [
				["server_id", "IS NOT", NULL],
			],
			"order_by" => [
				"created" => "DESC",
			],
			"limit" => 1,
		]);
		$this->or["server_id"] = $server['server_id'];

		# We're only interested in currently open connections
		$this->where = [
			"closed" => NULL,
		];

		# Reset the join array
		$this->join = [];

		# User permissions based recipients
		$this->getRecipientsBasedOnUserPermissions($a);

		# Role based recipients
		$this->getRecipientsBasedOnRolePermissions($a);

		# Requester based recipient (there is only one requester at any time)
		$this->getRecipientsBasedOnRequester($a);

		/**
		 * In cases where the registered user or client
		 * doesn't have WebSockets enabled, their connection
		 * row will not have a server ID or an FD number.
		 *
		 * If we're getting those connections, we must also
		 * include those rows where the server ID is null.
		 *
		 * However, this only applies to when we are looking
		 * for an explicit user or session.
		 */
		if($user_id || $session_id){
			# We're only interested in one connection
			$limit = 1;

			# And it should be with a server ID, the newest first
			$order_by = [
				"server_id" => "DESC",
				"created" => "DESC"
			];

			$this->or[] = ["server_id", "IS", NULL];
		}

		else {
			/**
			 * If we're not looking for a particular user
			 * or session (non-registered user, like a client),
			 * we are only interested in WebSocket connections,
			 * those with FD numbers.
			 */
			$this->where[] = ["fd", "IS NOT", NULL];
		}

		if(!$connections = $this->sql->select([
			"table" => "connection",
			"join" => $this->join,
			"where" => $this->where,
			"or" => $this->or,
			"limit" => $limit,
			"order_by" => $order_by,
		])){
			return NULL;
		}

		# Always make sure the connections array is numerical
		if(!str::isNumericArray($connections)){
			$connections = [$connections];
		}

		return $connections;
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
			],
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
				"rel_table" => $role,
			],
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
		}

		if($session_id){
			$this->where['session_id'] = $session_id;
		}
	}

	/**
	 * Push a message to a given list of recipients.
	 *
	 * @param array $fd      A numerical array of recipients (`fd` numbers)
	 * @param array $message An array of messages, direction, instructions.
	 *
	 * @throws \Exception
	 */
	public function push(array $fd, array $message): void
	{
		if(empty($fd)){
			throw new \Exception("No recipients provided.");
		}
		if(empty($message)){
			throw new \Exception("No message provided.");
		}

		//		if(str::runFromCLI()){
		//			//If this method is called from the CLI
		//
		//			/**
		//			 * The below needs to be in the go() function because Swoole said so
		//			 * @link https://www.qinziheng.com/swoole/7477.htm
		//			 */
		//			go(function() use($fd, $message){
		//				$client = new Client($_ENV['websocket_internal_ip'], $_ENV['websocket_internal_port']);
		//				$client->upgrade("/");
		//				$client->push(json_encode([
		//					"fd" => $fd,
		//					"data" => $message
		//				]));
		//				$client->close();
		//			});
		//
		//			return;
		//		}

		/**
		 * For some reason, this doesn't work. So we're doing a hack-y version below,
		 * where data > 900 chars is stored in a file and the file link is sent instead.
		 * This is to avoid hitting shell_exec max string limits.
		 *
		 * StackOverflow ticket:
		 * @link https://stackoverflow.com/q/67708754/429071
		 */

		# Prepare the data as a single commandline friendly json string
		$data = urlencode(json_encode([
			"fd" => $fd,
			"data" => $message,
		]));

		# Place the whole thing in a co-routine
		$cmd = "'go(function(){";

		# Will eventually need to change to the following in Swoole 4.6+
		//		$cmd  = "'\\Swoole\\Coroutine\\run(function(){";

		# Fire up the http client
		$cmd .= "\$client = new \\Swoole\\Coroutine\\Http\\Client(\"{$_ENV['websocket_internal_ip']}\", \"{$_ENV['websocket_internal_port']}\");";

		# Upgrade the connection (not exactly sure why)
		$cmd .= "\$client->upgrade(\"/\");";

		# If the data being sent is larger than 900 chars, create a tmp file and send the file link instead
		if(strlen($data) > 900){
			//if more than 900 chars is being sent
			$filename = $_ENV['tmp_dir'] . rand();
			file_put_contents($filename, $data);
			$cmd .= "\$client->push(urldecode(file_get_contents(\"{$filename}\")));unlink(\"{$filename}\");";
		}

		# For short messages, just sent the data
		else {
			$cmd .= "\$client->push(urldecode(\"{$data}\"));";
		}

		# Close the connection
		$cmd .= "\$client->close();";

		# And we're done
		$cmd .= "});'";

		# Execute the command
		$output = shell_exec("php -r {$cmd} 2>&1");

		# If there is any output, that's bad news
		if($output){
			throw new \Exception($output);
		}
	}
}

/**
 * To estimate the shell_exec character limit, the following code was run. It turned out
 * to be incorrect and a far shorter limit (of ~900 chars) was established.
 *
 * function generateRandomString($length = 25) {
 *    $characters = '0123456789';
 *    $charactersLength = strlen($characters);
 *    $randomString = '';
 *    for ($i = 0; $i < $length; $i++) {
 *        $randomString .= $characters[rand(0, $charactersLength - 1)];
 *    }
 *    return $randomString;
 * }
 *
 * # Our starting point
 * $times = 130000;
 *
 * while(true){
 *    $output = @shell_exec("echo ".generateRandomString($times));
 *    if(!preg_match("/[^0-9]+/", $output)){
 *        print "Can't do ".$times;
 *        exit;
 *    }
 *    $times += 1;
 * }
 */