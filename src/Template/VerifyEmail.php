<?php


namespace App\Common\Template;

use App\Common\href;
use App\Common\str;
use App\UI\Grid;

/**
 * Class VerifyEmail
 * Email sent to anyone who registers, to verify
 * their email address.
 * @package App\Common\Template
 */
class VerifyEmail extends Template implements TemplateInterface {

	/**
	 * @inheritDoc
	 */
	public function getSubject(): string
	{
		return "Welcome to {$_ENV['title']}, please verify your email";
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
			"action" => "verify_email",
			"vars" => [
				"email" => $email,
				"key" => $key,
			],
		], NULL, true);

		$link = href::a([
			"html" => $url,
			"url" => $url
		]);

		return [[
			"bg_colour" => "silent",
			"copy" => [
				"title" => [
					"align" => "left",
					"title" => "Welcome to {$_ENV['title']}!",
					"colour" => "primary",
				],
				"body" => [
					"body" => "{$first_name}, thank you for registering with {$_ENV['title']}!<br/>
				To continue your registration, please verify your email
				address by clicking on the button below.",
					"align" => "left"
				]
			],
			"button" => [
				"colour" => "primary",
				"title" => "Verify email",
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
		return "Complete your registration, verify your email.";
	}
}