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
	public function __construct (?string $command = NULL)
	{
		if ($command) {
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
			$params_json = str_replace(["\\", '"'],["\\\\",'\\"'],json_encode($params));
			$params = "\"{$params_json}\"";
			//Both \ and " must be escaped or else the command will fail
		}

		# Build the command that executes the execute method
		$cmd  = "go(function(){";
		$cmd .= "require \"/var/www/html/app/settings.php\";";
		$cmd .= "\$instance = new {$class}();";
		$cmd .= "\$instance->{$method}({$params});";
		$cmd .= "});";

		# Use the Process class to execute it with a pID that can be checked
		$process = new Process("php -r '{$cmd}'");

		return $process->getPid();
	}

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
	 * This should be used for any method that can be reached via `rel_table/rel_id/action`.
	 *
	 * @param array $a
	 *
	 * @return int The process ID
	 */
	public static function request(array $a): int
	{
		# Get the requester params
		$requester = self::getGlobalVariablesAsString();

		# Format the params to feed to the method
		$params = self::stringifyArray($a);

		# Build the command that executes the execute method
		$cmd  = "go(function(){";
		$cmd .= "require \"/var/www/html/app/settings.php\";";
		$cmd .= "\$request = new App\\Common\\Request({$requester});";
		$cmd .= "\$request->handler({$params});";
		$cmd .= "});";

		# Use the Process class to execute it with a pID that can be checked
		$process = new Process("php -r '{$cmd}'");

		return $process->getPid();
	}

	/**
	 * Returns the user ID and role (if the user is logged in),
	 * and the session ID (for all users) as a string that
	 * can be fed into a CLI executed php -r command.
	 * @return string
	 */
	private static function getGlobalVariablesAsString(): string
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

		# Session ID (will always exist)
		$global_vars['session_id'] = session_id();

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
	 */
	private static function stringifyArray($a){
		$params = "[";
		foreach($a as $key => $val){
			$key = str_replace('"', '\\"', $key);
			if(is_array($val)){
				$val = self::stringifyArray($val);
				$params .= "\"{$key}\" => {$val},";
			} else {
				$val = str_replace('"', '\\"', $val);
				$params .= "\"{$key}\" => \"{$val}\",";
			}
		}
		$params .= "]";
		return $params;
	}

	private function runCommand (): void
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
	public function setPid ($pid)
	{
		$this->pid = $pid;
	}

	public function getPid ()
	{
		return $this->pid;
	}

	/**
	 * @return bool
	 */
	public function status ()
	{
		$command = 'ps -p ' . $this->pid;
		exec($command, $op);
		if (!isset($op[1])) return false;
		else return true;
	}

	/**
	 * @return bool
	 */
	public function start ()
	{
		if ($this->command != '') $this->runCommand();
		else return true;
	}

	/**
	 * @return bool
	 */
	public function stop ()
	{
		$command = 'kill ' . $this->pid;
		exec($command);
		if ($this->status() == false) return true;
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
			throw new \Exception("There was an error aborting the process: ".implode("<br>", array_filter($output)));
		}
		return true;
	}
}