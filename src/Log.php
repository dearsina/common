<?php


namespace App\Common;


use App\Common\SQL\Factory;
use App\UI\Icon;

/**
 * Class log
 * @package App\Common
 */
class Log {
	private $alerts = [];
	private $script_start_time;

	/**
	 * The constructor is private so that the class
	 * can be run in static mode.
	 *
	 * Cloning and wakeup are also set to private to prevent
	 * cloning and unserialising of the Hash() object.
	 */
	private function __construct() {
		$this->startTimer();
	}
	private function __clone() {}
	private function __wakeup() {}

	/**
	 * Used instead of new to ensure that the same instance is used every time it's initiated.
	 * @return Log
	 * @link http://stackoverflow.com/questions/3126130/extending-singletons-in-php
	 */
	final public static function getInstance(): Log
	{
		static $instance = null;
		if (!$instance) {
			$instance = new Log();
		}
		return $instance;
	}

	public function startTimer(){
		$this->script_start_time = microtime(true);
	}

	/**
	 * Returns all allerts, of false if none are found.
	 *
	 * @return mixed
	 */
	public function getAlerts () {
		if(!empty($this->alerts)){
			foreach($this->alerts as $type => $alerts){
				foreach($alerts as $alert){
					$flat_error_array[] = str::array_filter_recursive(
						array_merge($alert,$this->prepareAlert($type, $alert))
					);
				}
			}
			return $flat_error_array;
		}
		return false;
	}

	/**
	 * Given an array type and the particulars,
	 * prepare an array for the front end.
	 *
	 * @param string $type
	 * @param array  $alert
	 *
	 * @return array
	 */
	public function prepareAlert (string $type, array $alert): array
	{
		$icon = Icon::getArray($alert['icon']);
		$alert["type"] = $type;
		$alert["icon"] = "{$icon['type']} fa-{$icon['name']}";
		unset($alert['backtrace']);
		return $alert;
	}

	public function clearAlerts(){
		$this->alerts = [];
	}

	/**
	 * Gets the highest current error status,
	 * or false if no messages have been logged.
	 *
	 * @return bool|string
	 */
	public function getStatus(){
		if(!is_array($this->alerts)){
			return false;
		}

		$types = array_keys($this->alerts);

		if(in_array("error", $types)){
			return "error";
		}
		if(in_array("warning", $types)){
			return "warning";
		}
		if(in_array("info", $types)){
			return "info";
		}
		if(in_array("success", $types)){
			return "success";
		}
		return false;
	}

	/**
	 * Gets the number of seconds since start.
	 *
	 * @return float
	 */
	public function getDuration(){
		return $this->secondsSinceStart();
	}

	private $failure_error_types = [
		"danger",
		"error",
		"red"
	];

	/**
	 * Checks to see if the log contain alerts that are considered failures (show stoppers).
	 * Returns boolean true if so.
	 *
	 * @return bool
	 */
	public function hasFailures() {
		if(!$this->alerts){
			return false;
		}
		if(count(array_intersect($this->failure_error_types,array_keys($this->alerts)))) {
			//if any error (types) are deemed as failures
			return true;
		}
		//only benign errors (warnings, alerts, info, etc)
		return false;
	}

	/**
	 * Stores all failure messages in the DB.
	 */
	public function logFailures(){
		if(!is_array($this->alerts)){
			return false;
		}

		foreach($this->alerts as $type => $alerts){
			if(!in_array($type,$this->failure_error_types)){
				continue;
			}
			foreach($alerts as $alert){
				$this->logAlert($type, $alert);
			}
		}

		return true;
	}

	/**
	 * Logs a single alert.
	 *
	 * @param string $type
	 * @param array  $alert
	 */
	private function logAlert (string $type, array $alert): void
	{
		$icon = Icon::getArray($alert['icon']);

		$sql = Factory::getInstance();
		// Has to be initiated "locally" to prevent an infinite loop

		$alert_array = array_filter([
			"type" => $type,
			"title" => $alert['title'],
			"message" => implode("\r\n\r\n", array_filter([$alert['message'], $alert['backtrace']])),
			"icon" => "{$icon['type']} fa-{$icon['name']}",
			"seconds" => $alert['seconds'],
			"action" => $_REQUEST['action'],
			"rel_table" => $_REQUEST['rel_table'],
			"rel_id" => $_REQUEST['rel_id'],
			"vars" => $_REQUEST['vars'] ? json_encode($_REQUEST['vars']) : NULL,
			"connection_id" => $_SERVER['HTTP_CSRF_TOKEN']
		]);

		# Insert the error in the DB
		$sql->insert([
			"table" => 'error_log',
			"set" => $alert_array
		]);
	}

	/**
	 * A unique method only called by the error.php file
	 * in the root of the project, when an error occurs
	 * on the ajax.php pathway.
	 */
	public static function connectionError() : void
	{
		# Load up SQL
		$sql = Factory::getInstance();
		// Has to be initiated "locally" to prevent an infinite loop

		foreach($_REQUEST as $key => $val){
			# We're only interested in a limited set of keys
			if(!in_array($key, ["rel_table", "rel_id", "action", "vars", "title", "message"])){
				continue;
			}
			# If the val is marked as "false", treat it as a FALSE
			if($val == "false"){
				continue;
			}
			# If the val is an array, convert it to a (JSON) string
			if(is_array($val)){
				$val = json_encode($val);
			}
			# Set the values in an array to load into SQL
			$set[$key] = $val;
		}

		# Insert the error in the DB
		if(!$sql->insert([
			"table" => 'error_log',
			"set" => $set
		])){
			echo "There was a problem logging your error.";
		}

		# Exit stage right
		exit;
	}

	/**
	 * Returns the number of seconds since start.
	 *
	 * @return float
	 */
	private function secondsSinceStart(){
		$now = microtime(true);
		return round($now - $this->script_start_time,3);
	}

	/**
	 * Log an message of any type
	 * <code>
	 * $this->log([
	 *    "type" => "",
	 *    "icon" => $icon,
	 *    "title" => $title,
	 *    "message" => $message
	 * ]);
	 * </code>
	 *
	 * @param string|array $a
	 *
	 * @param string $type
	 *
	 * @param mixed   $immediately
	 *
	 * @return bool
	 */
	public function log($a, string $type, $immediately){
		$alert = is_array($a) ? $a : ["message" => $a];

		if(!$alert['icon']){
			$alert['icon'] = Icon::DEFAULTS[$type];
		}

		$alert['seconds'] = $this->secondsSinceStart();
		$alert['backtrace'] = str::backtrace(true);

		# If the alert is to be shared with the user immediately
		if($immediately){
			return $this->alertImmediately($type, $alert, $immediately);
		}
		$this->alerts[$type][] = $alert;
		return true;
	}

	/**
	 * Logs and sends an alert out immediately
	 * to one or many users via WebSockets.
	 *
	 * @param string     $type
	 * @param array      $alert
	 * @param mixed $immediately
	 *
	 * @return bool
	 */
	private function alertImmediately(string $type, array $alert, $immediately = NULL){
		# Log the alert (if of a failure type)
		if(in_array($type,$this->failure_error_types)){
			$this->logAlert($type, $alert);
		}

		# Prepare the alert for sharing with users
		$alert = $this->prepareAlert($type, $alert);

		# Prepare the message
		if(is_array($immediately)){
			$speak = $immediately;
		}
		$speak['data']['alerts'][] = $alert;

		# Send the message to the recipients
		$pa = PA::getInstance();
		return $pa->speak($speak);
	}

	/**
	 * Raise an error
	 * <code>
	 * $this->log->error("Error message");
	 * $this->log->error([
	 *    "title" => "Error title",
	 *    "message" => "Error message"
	 * ]);
	 * </code>
	 *
	 * @param           $a
	 *
	 * @param mixed $immediately
	 *
	 * @return true
	 */
	function error($a, $immediately = NULL){
		return $this->log($a, __FUNCTION__, $immediately);
	}

	/**
	 * Raise an warning
	 * <code>
	 * $this->log->warning("Warning message");
	 * $this->log->warning([
	 *    "title" => "Warning title",
	 *    "message" => "Warning message"
	 * ]);
	 * </code>
	 *
	 * @param      $a
	 *
	 * @param mixed $immediately
	 *
	 * @return true
	 */
	function warning($a, $immediately = NULL){
		return $this->log($a, __FUNCTION__, $immediately);
	}

	/**
	 * Raise an info
	 * <code>
	 * $this->log->info("Info message");
	 * $this->log->info([
	 *    "title" => "Info title",
	 *    "message" => "Info message"
	 * ]);
	 * </code>
	 *
	 * @param      $a
	 *
	 * @param mixed $immediately
	 *
	 * @return true
	 */
	function info($a, $immediately = NULL){
		return $this->log($a, __FUNCTION__, $immediately);
	}

	/**
	 * Raise an success
	 * <code>
	 * $this->log->success("Success message");
	 * $this->log->success([
	 *    "title" => "Success title",
	 *    "message" => "Info message"
	 * ]);
	 * </code>
	 *
	 * @param      $a
	 *
	 * @param mixed $immediately
	 *
	 * @return true
	 */
	function success($a, $immediately = NULL){
		return $this->log($a, __FUNCTION__, $immediately);
	}

}