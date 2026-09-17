<?php

namespace App\Common\Exception;

use App\Common\str;

class MySqlException extends Prototype {

	const PUBLIC_MESSAGE = "There seems to be an issue connecting to our servers.
	Please try again shortly. Apologies for any inconvenience this may have caused.";

	/**
	 * Prevent an SQL error raised while logging another SQL error from recursively
	 * trying to write to the same database.
	 */
	private static bool $logging_exception = false;

	/**
	 * Create an exception for a failed connection without trying to log it to the
	 * database that has just failed. The private details still go to PHP's error log.
	 */
	public static function fromConnectionFailure(string $private_message, $code, \Throwable $previous): self
	{
		return new self($private_message, $code, $previous, false);
	}

	/**
	 * MySqlException constructor.
	 *
	 * Handles showing a generic (and not scary) error to the end user,
	 * while logging the actual error for the admins.
	 *
	 * @param string|null     $private_message The message you want to log for the admins.
	 * @param int             $code            The http response code to issue with this error, default 400
	 * @param \Throwable|null $previous
	 * @param bool            $log_to_database
	 */
	public function __construct(?string $private_message = NULL, $code = 400, ?\Throwable $previous = NULL, bool $log_to_database = true)
	{
		$private_log_message = $private_message ? $private_message." [A different message was shown to the user.]" : self::PUBLIC_MESSAGE;

		if(!$log_to_database || self::$logging_exception){
			self::logToPhpErrorLog($private_log_message, $code, $previous);
		}
		else {
			self::$logging_exception = true;

			try {
				self::logException("mySQL exception", $private_log_message, $code);
			}
			catch(\Throwable $logging_error) {
				// If the database logger itself fails, fall back without recursing.
				self::logToPhpErrorLog($private_log_message, $code, $logging_error);
			}
			finally {
				self::$logging_exception = false;
			}
		}

		# Show the public message to the end user (unless we are in dev mode, in which case show the private message)
		parent::__construct(str::isDev() ? ($private_message ?: self::PUBLIC_MESSAGE) : self::PUBLIC_MESSAGE, (int)$code, $previous);
	}

	private static function logToPhpErrorLog(string $message, $code, ?\Throwable $cause = NULL): void
	{
		$log_message = "mySQL exception [".(int)$code."]: {$message}";

		if($cause){
			$log_message .= PHP_EOL."Caused by: ".$cause;
		}

		error_log($log_message);
	}
}
