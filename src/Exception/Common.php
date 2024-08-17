<?php


namespace App\Common\Exception;


use App\Common\SQL\Factory;

/**
 * Exceptions common methods.
 * Extend any exceptions from this abstract class.
 * Class Common
 * @package App\Common\Exception
 */
abstract class Common extends \Exception {
	/**
	 * Logs an exception. Does not notify the end user.
	 *
	 * @param string $exception_type
	 * @param string $message
	 */
	public static function logException(string $exception_type, string $message, ?int $code = NULL): void
	{
		$sql = Factory::getInstance("mySQL", true);

		$alert_array = array_filter([
			"title" => $exception_type,
			"code" => $code,
			"message" => $message,
			"subdomain" => $_REQUEST['subdomain'],
			"action" => $_REQUEST['action'],
			"rel_table" => $_REQUEST['rel_table'],
			"rel_id" => $_REQUEST['rel_id'],
			"vars" => $_REQUEST['vars'] ? json_encode($_REQUEST['vars']) : NULL,
			"connection_id" => $_SERVER['HTTP_CSRF_TOKEN'],
		]);

		# Insert the error in the DB
		$sql->insert([
			"log" => false,
			"table" => "error_log",
			"set" => $alert_array,
			"reconnect" => true // Reconnects in case the error was caused by a long running script
		]);
	}
}