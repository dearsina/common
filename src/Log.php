<?php


namespace App\Common;


/**
 * Class log
 * @package App\Common
 */
class Log {

	protected static $instance = false;
	private $alerts = [];
	private $script_start_time;

	protected function __construct() {
		$this->start_timer();
	}

	private function __clone() {
		// Stopping cloning of object
	}

	private function __wakeup() {
		// Stopping unserialize of object
	}

	/**
	 * Used instead of new to ensure that the same instance is used every time it's initiated.
	 * @return Log
	 * @link http://stackoverflow.com/questions/3126130/extending-singletons-in-php
	 */
	final public static function getInstance () {
		static $instance;
		if(!isset($instance)) {
			$calledClass = get_called_class();
			$instance = new $calledClass();
		}

		return $instance;
	}

	public function start_timer(){
		$this->script_start_time = microtime(true);
	}

	/**
	 * Returns errors, if any, otherwise returns boolean false
	 * @return mixed
	 */
	public function get_alerts () {
		if(!empty($this->alerts)){
			foreach($this->alerts as $type => $alerts){
				foreach($alerts as $alert){
					$icon = Icon::get_array($alert['icon']);
					$flat_error_array[] = [
						"type" => $type,
						"title" => $alert['title'],
						"message" => $alert['message'],
						"icon" => "{$icon['type']} fa-{$icon['name']}",
						"seconds" => $alert['seconds'],
					];
				}
			}
			return $flat_error_array;
		}
		return false;
	}

	public function clear_alerts(){
		$this->alerts = [];
	}

	/**
	 * Gets the highest current error status,
	 * or false if no messages have been logged.
	 *
	 * @return bool|string
	 */
	public function get_status(){
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
	public function get_duration(){
		return $this->seconds_since_start();
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
	public function has_failures() {
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
	public function log_failures(){
		$sql = sql::getInstance();

		if(!is_array($this->alerts)){
			return false;
		}

		foreach($this->alerts as $type => $alerts){
			if(!in_array($type,$this->failure_error_types)){
				continue;
			}
			foreach($alerts as $alert){
				$icon = Icon::get_array($alert['icon']);

				$alert_array = [
					"type" => $type,
					"title" => $alert['title'],
					"message" => implode("\r\n\r\n", array_filter([$alert['message'], $alert['backtrace']])),
					"icon" => "{$icon['type']} fa-{$icon['name']}",
					"seconds" => $alert['seconds'],
					"action" => $_REQUEST['action'],
					"rel_table" => $_REQUEST['rel_table'],
					"rel_id" => $_REQUEST['rel_id'],
					"vars" => $_REQUEST['vars'] ? http_build_query($_REQUEST['vars']) : NULL
				];

				# Insert the error in the DB
				if(!$insert_id = $sql->insert([
					"table" => 'error_log',
					"set" => $alert_array
				])){
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Returns the number of seconds since start.
	 *
	 * @return float
	 */
	private function seconds_since_start(){
		$now = microtime(true);
		return round($now - $this->script_start_time,3);
	}

	/**
	 * Log an message of any type
	 * <code>
	 * $this->log([
	 * 	"type" => "",
	 * 	"icon" => $icon,
	 * 	"title" => $title,
	 * 	"message" => $message
	 * ]);
	 * </code>
	 *
	 * @param $a array
	 *
	 * @return bool
	 */
	private function log($a){
		extract($a);
		$alert = [
			"icon" => $icon,
			"title" => $title,
			"message" => $message,
			"seconds" => $this->seconds_since_start(),
			"backtrace" => $backtrace ?: str::backtrace(true)
		];
		$this->alerts[$type][] = $alert;
		return true;
	}

	/**
	 * Raise an error
	 * <code>
	 * $this->log->error("Error message");
	 * $this->log->error([
	 * 	"title" => "Error title",
	 * 	"message" => "Error message"
	 * ]);
	 * </code>
	 *
	 * @param $a
	 *
	 * @return true
	 */
	function error($a){
		if(is_array($a)){
			extract($a);
			if($headline){
				$title = $headline;
			}
			if($msg){
				$message = $msg;
			}
			if(!$icon){
				$icon = Icon::DEFAULTS['error'];
			}
		} else {
			// Default values
			$title = "Error";
			$icon = Icon::DEFAULTS['error'];
			$message = $a;
		}

		return $this->log([
			"type" => "error",
			"icon" => $icon,
			"title" => $title,
			"message" => $message,
			"backtrace" => $backtrace
		]);
	}

	/**
	 * Raise an warning
	 * <code>
	 * $this->log->warning("Warning message");
	 * $this->log->warning([
	 * 	"title" => "Warning title",
	 * 	"message" => "Warning message"
	 * ]);
	 * </code>
	 *
	 * @param $a
	 *
	 * @return true
	 */
	function warning($a){
		if(is_array($a)){
			extract($a);
			if($headline){
				$title = $headline;
			}
			if($msg){
				$message = $msg;
			}
			if(!$icon){
				$icon = Icon::DEFAULTS['warning'];
			}
		} else {
			$title = "Warning";
			$icon = Icon::DEFAULTS['warning'];
			$message = $a;
		}

		return $this->log([
			"type" => "warning",
			"icon" => $icon,
			"title" => $title,
			"message" => $message
		]);
	}

	/**
	 * Raise an info
	 * <code>
	 * $this->log->info("Info message");
	 * $this->log->info([
	 * 	"title" => "Info title",
	 * 	"message" => "Info message"
	 * ]);
	 * </code>
	 *
	 * @param $a
	 *
	 * @return true
	 */
	function info($a) {
		if(is_array($a)){
			extract($a);
			if($headline){
				$title = $headline;
			}
			if($msg){
				$message = $msg;
			}
			if(!$icon){
				$icon = Icon::DEFAULTS['info'];
			}
		} else {
			$title = "Notice";
			$icon = Icon::DEFAULTS['info'];
			$message = $a;
		}

		return $this->log([
			"type" => "info",
			"icon" => $icon,
			"title" => $title,
			"message" => $message
		]);
	}

	/**
	 * Raise an success
	 * <code>
	 * $this->log->success("Success message");
	 * $this->log->success([
	 * 	"title" => "Info title",
	 * 	"message" => "Info message"
	 * ]);
	 * </code>
	 *
	 * @param $a
	 *
	 * @return true
	 */
	function success($a) {
		if(is_array($a)){
			extract($a);
			if($headline){
				$title = $headline;
			}
			if($msg){
				$message = $msg;
			}
			if(!$icon){
				$icon = Icon::DEFAULTS['success'];
			}
		} else {
			$title = "Great success!";
			$icon = Icon::DEFAULTS['success'];
			$message = $a;
		}

		return $this->log([
			"type" => "success",
			"icon" => $icon,
			"title" => $title,
			"message" => $message
		]);
	}

}