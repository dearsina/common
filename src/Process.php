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

	private function runCom ()
	{
		$command = 'nohup ' . $this->command . ' > /dev/null 2>&1 & echo $!';
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