<?php


namespace App\Common\Template;


use App\Common\href;
use App\Common\str;
use App\UI\Grid;

/**
 * Class ResetPassword
 * 
 * Email sent to those who wish to reset their password,
 * to verify that they have access to the email address,
 * and to send them their custom link to set up
 * a new password.
 * 
 * @package App\Common\Email\Template
 */
class ResetPassword extends Template implements TemplateInterface {

	/**
	 * @inheritDoc
	 */
	public function getSubject() : string
	{
		return "Reset your password to {$_ENV['title']}";
	}

	/**
	 * @inheritDoc
	 */
	public function getBody() : array
	{
		extract($this->getVariables());

		$url = $this->getDomain();
		$url .= str::generate_uri([
			"rel_table" => "user",
			"rel_id" => $user_id,
			"action" => "new_password",
			"vars" => [
				"key" => $key
			]
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
					"title" => "Reset your password for {$_ENV['title']}",
					"colour" => "primary",
				],
				"body" => [
					"body" => ["A request to reset the password on your account was received.
					If this was unexpected, please delete this email and let us know right away.
					Your account and password will remain safe and unchanged.",
						"To reset your password, please click on the button below.
					This link will expire if not used."],
					"align" => "left"
				]
			],
			"button" => [
				"colour" => "primary",
				"title" => "Reset password",
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
		return "Password reset link will expire.";
	}
}