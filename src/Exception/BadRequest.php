<?php


namespace App\Common\Exception;


/**
 * Class BadRequest
 * @package App\Common\Exception
 */
class BadRequest extends Prototype {
	/**
	 * Bad Request constructor.
	 *
	 * @param string          $public_message  The message you want to sent back to the API requester
	 * @param string|null     $private_message The message you want to log for the admins.
	 * @param int             $code            The http response code to issue with this error, default 400
	 * @param \Exception|null $previous
	 */
	public function __construct(string $public_message, ?string $private_message = NULL, $code = 400, \Exception $previous = NULL)
	{
		self::logException("Bad Request", $private_message ?: $public_message, $code);
		parent::__construct($public_message, $code, $previous);
	}
}