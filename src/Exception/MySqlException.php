<?php

namespace App\Common\Exception;

use App\Common\str;

class MySqlException extends Prototype {

	const PUBLIC_MESSAGE = "There seems to be an issue connecting to our servers.
	Please try again shortly. Apologies for any inconvenience this may have caused.";

	/**
	 * MySqlException constructor.
	 *
	 * Handles showing a generic (and not scary) error to the end user,
	 * while logging the actual error for the admins.
	 *
	 * @param string|null     $private_message The message you want to log for the admins.
	 * @param int             $code            The http response code to issue with this error, default 400
	 * @param \Exception|null $previous
	 */
	public function __construct(?string $private_message = NULL, $code = 400, \Exception $previous = NULL)
	{
		# Log the private message (if there is one)
		self::logException("mySQL exception", $private_message ? $private_message." [A different message was shown to the user.]" : self::PUBLIC_MESSAGE, $code);

		# Show the public message to the end user (unless we are in dev mode, in which case show the private message)
		parent::__construct(str::isDev() ? $private_message : self::PUBLIC_MESSAGE, $code, $previous);
	}
}