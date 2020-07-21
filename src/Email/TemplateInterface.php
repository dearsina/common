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
	 *
	 *
	 * @return string Expects a string that will act as the subject line. That's it.
	 */
	public function getSubject() : string;

	/**
	 * Returns the message main body only,
	 * which will be combined with an optional
	 * top and tail HTML to produce the
	 * complete message HTML.
	 *
	 * @return string Expects the entire body HTML, to be placed between the HTML top and tail.
	 */
	public function getMessage() : string;
}