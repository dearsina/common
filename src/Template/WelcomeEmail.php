<?php


namespace App\Common\Template;


use App\Common\href;
use App\Common\str;

class WelcomeEmail extends Template implements TemplateInterface {

	/**
	 * Returns the email subject line.
	 * @return string A string that will act as the subject line. That's it.
	 */
	public function getSubject(): string
	{
		return "Welcome to {$_ENV['title']}";
	}

	/**
	 * The preheader is single line of text that is presented to the user when browsing their inbox,
	 * right underneath the subject line. Useful to give context to what the email contains.
	 * @return string
	 */
	public function getPreheader(): ?string
	{
		return "Get started with your new account.";
	}

	/**
	 * Returns the email message body.
	 * @return array Building blocks for the message body that will be placed between the top and tails of App specific custom wrappers.
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
					"title" => "Welcome to {$_ENV['title']}!",
					"colour" => "primary",
				],
				"body" => [
					"body" => "An account has been set up for you. Please press the button below to verify your email address and set up a password.",
					"align" => "left"
				]
			],
			"button" => [
				"colour" => "primary",
				"title" => "Verify email",
				"url" => $url
			]
		],[
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
}