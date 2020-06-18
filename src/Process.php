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
	 * @param bool $cl
	 */
	public function __construct ($cl = false)
	{
		if ($cl != false) {
			$this->command = $cl;
			$this->runCom();
		}
	}

	/**
	 * Given a hash array, will execute the hash
	 * as a separate thread.
	 *
	 * @param array      $a
	 * @param array|null $params
	 *
	 * @return int Returns the process ID (piD) of the thread created.
	 *
	 * @throws \Exception
	 */
	public static function generate (array $a, ?array $params = NULL): int
	{
		extract($a);

		if(!$rel_table && !$action){
			throw new \Exception("A valid hash array must be presented to generate a thread.");
		}

		if(!$class = str::findClass($rel_table)){
			//if a class doesn't exist
			unset($a['vars']);
			throw new \Exception("No matching class for <code>".str::generate_uri($a)."</code> can be found.");
		}

		# Create a new instance of the class (to ensure the method exists)
		$instance = new $class();

		# Set the method (view is the default)
		$method = str::getMethodCase($action) ?: "view";

		# Ensure the method is available
		if(!str::methodAvailable($instance, $method)){
			unset($a['vars']);
			throw new \Exception("The <code>".str::generate_uri($a)."</code> method doesn't exist or is not public.");
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

	private function runCom ()
	{
		$command = 'nohup ' . $this->command . ' > /tmp/process.alert 2>&1 & echo $!';
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
		if ($this->command != '') $this->runCom();
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
}