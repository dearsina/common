<?php


namespace App\Common\Email;

/**
 * Interface TemplateInterface
 * @package App\Common\Email
 */
interface TemplateInterface {
	/**
	 * Returns the email subject line.
	 *
	 * @return string
	 */
	public function getSubject() : string;

	/**
	 * Returns the message main body only,
	 * which will be combined with the
	 * header and footer to produce the
	 * complete message HTML.
	 * @return string
	 */
	public function getMessage() : string;
}