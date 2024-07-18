<?php


namespace App\Common\Template;


class TestEmail extends Template implements TemplateInterface {

	/**
	 * @inheritDoc
	 */
	public function getSubject(): string
	{
		return "Testing email server";
	}

	/**
	 * @inheritDoc
	 */
	public function getPreheader(): ?string
	{
		return "This is a test email to check the email server is working correctly.";
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
					"title" => "Test email sent at " . date("Y-m-d H:i:s")." UTC",
					"colour" => "primary",
				],
				"body" => [
					"body" => "This is a test email that was sent to check the email server credentials are correct
					and the email server is working correctly. If you have received this email, everything is in order.",
					"align" => "left"
				]
			],
		]];
	}
}