<?php

namespace App\Common\Exception;

class UnprocessableEntity extends Prototype {
	/**
	 * Unprocessable Entity constructor.
	 *
	 * The HyperText Transfer Protocol (HTTP) 422 Unprocessable Entity response status code indicates
	 * that the server understands the content type of the request entity, and the syntax of the
	 * request entity is correct, but it was unable to process the contained instructions.
	 *
	 * @param string          $public_message  The message you want to sent back to the API requester
	 * @param string|null     $private_message The message you want to log for the admins.
	 * @param int             $code            The http response code to issue with this error, default 400
	 * @param \Exception|null $previous
	 */
	public function __construct(string $public_message, ?string $private_message = NULL, $code = 422, \Exception $previous = NULL)
	{
		self::logException("Unprocessable Entity", $private_message ?: $public_message, $code);
		parent::__construct($public_message, $code, $previous);
	}
}