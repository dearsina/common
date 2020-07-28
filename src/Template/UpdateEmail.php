<?php


namespace App\Common\Template;


use App\Common\href;
use App\Common\str;
use App\UI\Grid;

/**
 * Class UpdateEmail
 * @package App\Common\Email\Template
 */
class UpdateEmail extends Template implements TemplateInterface {

	/**
	 * @inheritDoc
	 */
	public function getSubject(): string
	{
		return "Change of email address";
	}

	/**
	 * @inheritDoc
	 */
	public function getBody(): array
	{
		extract($this->getVariables());

		$url = $this->getDomain();
		$url .= str::generate_uri([
			"rel_table" => "user",
			"rel_id" => $user_id,
			"action" => "update_email",
			"vars" => [
				"new_email" => $new_email,
				"checksum" => $checksum,
			],
		]);

		$link = href::a([
			"html" => $url,
			"url" => $url
		]);

		return [[
			"bg_colour" => "silent",
			"copy" => [
				"title" => [
					"align" => "left",
					"title" => "Updating your email address",
					"colour" => "primary",
				],
				"body" => [
					"body" => ["To complete your request to update your email address to {$new_email}, press the button below.",
						"If this was unexpected, please delete this email and let us know right away.
					Your account and email will remain safe and unchanged."],
					"align" => "left"
				]
			],
			"button" => [
				"colour" => "primary",
				"title" => "Change email",
				"url" => $url
			]
		],[
			"bg_colour" => "silent",
			"copy" => [
				"body" => [
					"colour" => "grey",
					"body" => [
						"You can alternatively copy and paste the below link in your browser window.",
						$link
					],
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
		return "Includes link to confirm change.";
	}
}