<?php


namespace App\Common\Template;


interface TemplateInterface {
	/**
	 * Returns the email subject line.
	 * @return string A string that will act as the subject line. That's it.
	 */
	public function getSubject(): string;

	/**
	 * The preheader is single line of text that is presented to the user when browsing their inbox,
	 * right underneath the subject line. Useful to give context to what the email contains.
	 * @return string|null
	 */
	public function getPreheader(): ?string;

	/**
	 * Returns the email message body.
	 * @return string HTML message body that will be placed between the top and tails of App specific custom wrappers.
	 */
	public function getBody(): array;
}