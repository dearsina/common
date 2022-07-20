<?php

namespace App\Common\Exception;

class UnprocessableEntity extends Prototype {
	/**
	 * **Unprocessable Entity constructor.**
	 *
	 * The HyperText Transfer Protocol (HTTP) 422 Unprocessable Entity response status code indicates
	 * that the server understands the content type of the request entity, and the syntax of the
	 * request entity is correct, but it was unable to process the contained instructions.
	 *
	 * Error messages should be in the following _array_ format:
	 *
	 * <code>
	 * {
	 *    "status": "Document identification failed",
	 *    "errors": [
	 *        {
	 *            "file": "download (1).jfif",
	 *            "title": "Fields not found",
	 *            "message": "No ID number, Last name, Birth, Birth wildcard, Valid until or Valid wildcard found
	 *            in your SA Driving Licence document."
	 *        }
	 *    ]
	 * }
	 *
	 * @param array           $public_message_array The message you want to sent back to the API requester
	 * @param array|null      $private_message      The message you want to log for the admins.
	 * @param int             $code                 The http response code to issue with this error, default 400
	 * @param \Exception|null $previous
	 */
	public function __construct(array $public_message_array, ?array $private_message = NULL, $code = 422, \Exception $previous = NULL)
	{
		$public_message_json = json_encode($public_message_array);
		$private_message_json = $private_message ? json_encode($private_message) : NULL;

		self::logException("Unprocessable Entity", $public_message_json ?: $private_message_json, $code);
		parent::__construct($public_message_json, $code, $previous);
	}
}