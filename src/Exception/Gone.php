<?php

namespace App\Common\Exception;

use App\Common\Exception\Prototype;

class Gone extends Prototype {
	/**
	 * @param string          $public_message
	 * @param string|null     $private_message
	 * @param                 $code
	 * @param \Exception|NULL $previous
	 */
	public function __construct(string $public_message, ?string $private_message = NULL, $code = 410, \Exception $previous = NULL)
	{
		self::logException("Gone", $private_message ?: $public_message, $code);
		parent::__construct($public_message, $code, $previous);
	}
}