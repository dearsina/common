<?php


namespace App\Common\Template;

use App\UI\Grid;

/**
 * Class UpdateEmailWarning
 * @package App\Common\Email\Template
 */
class UpdateEmailWarning extends Template implements TemplateInterface {

	/**
	 * @inheritDoc
	 */
	public function getSubject(): string
	{
		return "Email address change request";
	}

	/**
	 * @inheritDoc
	 */
	public function getBody(): array
	{
		extract($this->getVariables());

		return [[
			"bg_colour" => "silent",
			"copy" => [
				"title" => [
					"align" => "left",
					"title" => "Notice of email changing",
					"colour" => "primary",
				],
				"body" => [
					"body" => ["This is a courtesy notice to inform you that a request to change your email address from {$email} to {$new_email} has been received and is being actioned.",
						"If this was unexpected, please let us know right away."],
					"align" => "left"
				]
			],
		]];
	}

	/**
	 * The preheader is single line of text that is presented to the user when browsing their inbox,
	 * right underneath the subject line. Useful to give context to what the email contains.
	 * @return string
	 */
	public function getPreheader(): ?string
	{
		return "Your email address is changing.";
	}
}