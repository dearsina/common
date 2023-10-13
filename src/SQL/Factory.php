<?php


namespace App\Common\SQL;

use App\Common\Email\Email;
use App\Common\Log;
use App\Common\SQL\mySQL\mySQL;

/**
 * Class Factory
 *
 * The SQL factory allows for easy switching
 * between different SQL flavours.
 *
 * @package App\Common\SQL
 */
class Factory {
	/**
	 * @param string $type
	 *
	 * @return mySQL
	 * @throws \Exception
	 */
	public static function getInstance(string $type = "mySQL", ?bool $api = NULL)
	{
		# Set the class depending on type type
		switch(strtolower($type)) {
		case 'mysql':
		default:
			$class = "mySQL";
		}

		# Create the full path
		$path = "App\\Common\\SQL\\{$class}\\{$class}";

		try {
			$sql = $path::getInstance();
		} catch(\Exception $e) {
			//If there is a SQL error

			# Notify info@, as this is a huge problem
			$email = new Email();
			$variables = [
				"ip" => $_SERVER['REMOTE_ADDR'],
				"error_message" => $e->getMessage(),
			];
			$email->template("database_down", $variables)
				->to("info@{$_ENV['domain']}")
				->send();

			$message = "An error has been detected and the system has become temporarily inaccessible. System engineers have been notified and the matter should be resolved shortly. Apologies for the inconvenience.";

			if($api){
				//If this is an API call

				# Send a out a 503 Service Unavailable error
				# The server cannot handle the request (because it is overloaded or down for maintenance). Generally, this is a temporary state.
				http_response_code(503);

				$output = [
					"status" => "Error",
					"message" => $message
				];

			} else {
				//If this is NOT an API call

				# Create output to the user with a GENERIC error message
				$log = Log::getInstance();
				$output['success'] = false;
				$log->setAlertToDisplayedAlertsArray($output['alerts'], "error", [
					"container" => "#ui-view",
					"icon" => "ethernet",
					"title" => "System error",
					"message" => $message
				]);
				$output['alerts'] = array_values($output['alerts']);
			}

			# Pencils down, wait for backup
			echo json_encode($output);
			exit;
		}

		return $sql;
	}
}