<?php

namespace App\Common\Exception;

class NotFound extends Prototype{
	/**
	 * @param string          $public_message
	 * @param string|null     $private_message
	 * @param                 $code
	 * @param \Exception|NULL $previous
	 */
	public function __construct(string $public_message, ?string $private_message = NULL, $code = 404, \Exception $previous = NULL)
	{
//		self::logException("Not Found", $private_message ?: $public_message, $code);
		// As this is a very common error, and isn't a fault of ours, there is no need to log it.
		parent::__construct($public_message, $code, $previous);
	}
}