<?php


namespace App\Common\Template;


class DatabaseDown extends Template implements TemplateInterface {

	/**
	 * @inheritDoc
	 */
	public function getSubject(): string
	{
		return "Database server down!";
	}

	/**
	 * @inheritDoc
	 */
	public function getPreheader(): ?string
	{
		return "User is unable to connect to the mySQL database server.";
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
					"title" => "mySQL database server down",
					"colour" => "primary",
				],
				"body" => [
					"body" => "A user [{$ip}] got the following error message when attempting to connect to the database server: <b>{$error_message}</b>.",
					"align" => "left"
				]
			],
		]];
	}
}