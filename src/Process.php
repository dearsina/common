<?php


namespace App\Common;


/**
 * Class Process
 *
 * Spin up a process separate from the current process.
 * Get the process PID. Linux only.
 *
 * @link    https://www.php.net/manual/en/function.exec.php#88704
 * @author  dell_petter at hotmail dot com
 * @package App\Common
 */
class Process {
	private $pid;
	private $command;

	/**
	 * Process constructor.
	 *
	 * @param string|null $command If a command is passed, the command will be executed
	 */
	public function __construct(?string $command = NULL)
	{
		if($command){
			$this->command = $command;
			$this->runCommand();
		}
	}

	/**
	 * Given a full class string and a method name,
	 * will feed the method the params and execute it
	 * asynchronously.
	 *
	 * The process will be run in CLI and be owner-less.
	 *
	 * This method should only be used to run class methods
	 * that cannot be reached via a URL (`rel_table/rel_id/action`).
	 * For all other methods, use the `Process::request` method
	 * instead. It retains ownership, and can feed back
	 * responses directly to the initiator.
	 *
	 * @param string     $class
	 * @param string     $method
	 * @param array|null $params
	 *
	 * @return int Returns the process ID (piD) of the thread created.
	 *
	 * @throws \Exception
	 */
	public static function execute(string $class, string $method, ?array $params = NULL): int
	{
		if(!class_exists($class)){
			throw new \Exception("Cannot find the <code>{$class}</code> class.");
		}

		# Create a new instance of the class (to ensure the method exists)
		$instance = new $class();

		# Ensure the method is available
		if(!str::methodAvailable($instance, $method)){
			throw new \Exception("The <code>{$method}</code> method doesn't exist in the <code>{$class}</code> class, or is not public.");
		}

		# Format the (optional) params to feed to the method
		if($params){
			$params_json = str_replace(["\\", '"'], ["\\\\", '\\"'], json_encode($params));
			$params = "\"{$params_json}\"";
			//Both \ and " must be escaped or else the command will fail
		}

		# Build the command that executes the execute method
		$cmd  = "\\Swoole\\Coroutine\\run(function(){";
		$cmd .= "require \"/var/www/html/app/settings.php\";";
		$cmd .= "\$instance = new {$class}();";
		$cmd .= "\$instance->{$method}({$params});";
		$cmd .= "});";

		# Use the Process class to execute it with a pID that can be checked
		$process = new Process("php -r '{$cmd}'");

		return $process->getPid();
	}

	const MAX_EXEC_CMD_LENGTH = 1024;

	/**
	 * Request a process asynchronously.
	 *
	 * This method should be used to offload
	 * tasks that are not instant to ensure that the
	 * user doesn't have to wait for requests
	 * to complete to continue their work.
	 *
	 * Because ownership (user ID, or session ID)
	 * is passed on to the request, it's as if
	 * the user themselves was performing the task.
	 *
	 * This should be used for any method that can
	 * be reached via `rel_table/rel_id/action`.
	 *
	 * If the params being sent is larger than the
	 * max number of chars allowed, a tmp file will
	 * be created with the data and the link
	 * will be included in the command instead.
	 *
	 * This is to avoid hitting shell_exec max string
	 * length limits.
	 *
	 * @param array    $a
	 * @param int|null $max_execution_time Set a different max execution time for the process (default is 30 seconds), set to 0 for no limit
	 *
	 * @return int The process ID
	 */
	public static function request(array $a, ?int $max_execution_time = NULL): int
	{
		# Get the requester params
		$requester = self::getGlobalVariables();

		# Format the params to feed to the method
		$params = self::stringifyArray($a);

		# Build the command that executes the execute method
		$cmd = "go(function(){";
		/**
		 * We're keeping this go(function(){}) instead of \Swoole\Coroutine\run(function(){})
		 * because the latter gives errors when trying to email using SwiftMailer:
		 * Fatal error: Uncaught Swoole\Error: API must be called in the coroutine
		 */
		$cmd .= "require \"/var/www/html/app/settings.php\";";

		# Set the max execution time if required
		if($max_execution_time !== NULL){
			$cmd .= "ini_set(\"max_execution_time\", {$max_execution_time});";
		}

		$cmd .= "\$request = new App\\Common\\Request({$requester});";

		# Long exec scripts
		if((strlen($cmd) + strlen($params)) > self::MAX_EXEC_CMD_LENGTH){
			//if more than the max number allowed is being sent

			# Create temporary filename name
			$filename = $_ENV['tmp_dir'] . rand();

			# Store the params in the temporary file
			file_put_contents($filename, serialize($a));

			# Get the handler to access the file containing the string
			$cmd .= "\$request->handler(unserialize(file_get_contents(\"{$filename}\")));";

			# Delete the temporary file
			$cmd .= "unlink(\"{$filename}\");";
		}

		# < 900 char params
		else {
			//If the params being sent is NOT larger than 900 chars

			$cmd .= "\$request->handler({$params});";
		}

		$cmd .= "});\\Swoole\\Event::wait();";

		# Use the Process class to execute it with a pID that can be checked
		$process = new Process("php -r '{$cmd}'");

		return $process->getPid();
	}

	/**
	 * Returns the user ID and role (if the user is logged in),
	 * and the session ID (for all users) as a string that
	 * can be fed into a CLI executed php -r command.
	 *
	 * @return string|array
	 */
	public static function getGlobalVariables(?bool $as_string = true)
	{
		# User ID (if exists)
		global $user_id;
		if($user_id){
			$global_vars['user_id'] = $user_id;
		}

		# Role (if exists)
		global $role;
		if($role){
			$global_vars['role'] = $role;
		}

		# Collect all the cookies
		foreach($_COOKIE as $key => $val){
			if(!is_array($val) && !is_object($val)){
				$global_vars[$key] = $val;
			}
		}

		# Connection ID
		global $connection_id;
		$global_vars['connection_id'] = $connection_id ?: $_SERVER['HTTP_CSRF_TOKEN'];
		// This is a more precise way to identify the recipient of thread output messages

		# Collect the session ID
		global $session_id;
		// If a global session ID is not set, set it to the current session ID
		$global_vars['session_id'] = $session_id ?: session_id();
		// This one is important as it will be used to identify the recipient of the output of the thread

		# IP
		$global_vars['ip'] = $_SERVER['REMOTE_ADDR'];
		// Process threads don't have an IP as they were initiated locally

		# Log the backtrace, but only in dev because it's heavy
		if(str::isDev()){
			# Get the global backtrace from the previous request
			global $backtrace;

			# Add the current backtrace to it
			$backtrace .= base64_encode(str::backtrace(true));

			# If it's not super long add it to the global vars
			if(strlen($backtrace) < self::MAX_EXEC_CMD_LENGTH){
				$global_vars['backtrace'] = $backtrace;
			}
		}

		if(!$as_string){
			return $global_vars;
		}

		# Return stringified
		return self::stringifyArray($global_vars);
	}

	/**
	 * Recursive function.
	 * Takes the normal $a array, or any array really,
	 * and stringifies it, and ensures that double quotes are used.
	 *
	 * @param $a
	 *
	 * @return string
	 * @link https://stackoverflow.com/a/65878993/429071
	 */
	private static function stringifyArray(array $a): string
	{
		$params = "[";
		foreach($a as $key => $val){
			# Format the key
			$key = str_replace('"', '\\"', $key);

			# Val arrays
			if(is_array($val)){
				$val = self::stringifyArray($val);
				$params .= "\"{$key}\" => {$val},";
			}

			# Val everything else
			else {
				# Escape any double quotes
				$val = str_replace('"', '\\"', $val);

				# Need to also escape any single quotes with their equivalent hex value
				$val = str_replace("'", "\\x27", $val);

				# Even empty values will be passed (by design)
				$params .= "\"{$key}\" => \"{$val}\",";
			}
		}
		$params .= "]";
		return $params;
	}

	private function runCommand(): void
	{
		$command = 'nohup ' . $this->command . ' >> /var/www/tmp/process.log 2>&1 & echo $!';

		# Prevent null byte injection warnings by stripping away the null byte character
		$command = str_replace(chr(0), "", $command);

		exec($command, $op);
		$this->pid = (int)$op[0];
	}

	/**
	 * @param $pid
	 */
	public function setPid($pid)
	{
		$this->pid = $pid;
	}

	public function getPid()
	{
		return $this->pid;
	}

	/**
	 * @return bool
	 */
	public function status()
	{
		$command = 'ps -p ' . $this->pid;
		exec($command, $op);
		if(!isset($op[1]))
			return false;
		else return true;
	}

	/**
	 * @return bool
	 */
	public function start()
	{
		if($this->command != '')
			$this->runCommand();
		else return true;
	}

	/**
	 * @return bool
	 */
	public function stop()
	{
		$command = 'kill ' . $this->pid;
		exec($command);
		if($this->status() == false)
			return true;
		else return false;
	}

	public static function abort(string $pid, ?bool $ignore_errors = NULL): bool
	{
		$command = "kill {$pid} 2>&1";
		exec($command, $output);
		if($ignore_errors){
			return true;
		}
		if($output){
			throw new \Exception("There was an error aborting the process: " . implode("<br>", array_filter($output)));
		}
		return true;
	}
}