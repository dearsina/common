<?php

namespace App\Common\Exception;

use App\Common\Exception\Prototype;
use App\Common\str;

class Gone extends Prototype {
	/**
	 * @param string          $public_message
	 * @param string|null     $private_message
	 * @param                 $code
	 * @param \Exception|NULL $previous
	 */
	public function __construct(string $public_message, ?string $private_message = NULL, $code = 410, \Exception $previous = NULL, ?bool $log = true)
	{
		$private_message = $private_message ?: $public_message;
		if($log){
			self::logException("Gone", $private_message, $code);
		}
		parent::__construct(str::isDev() ? $private_message : $public_message, $code, $previous);
	}
}