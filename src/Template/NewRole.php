<?php


namespace App\Common\Template;

class NewRole extends Template implements TemplateInterface {

	/**
	 * @inheritDoc
	 */
	public function getSubject(): string
	{
		return "You have been given a new role";
	}

	/**
	 * @inheritDoc
	 */
	public function getPreheader(): ?string
	{
		extract($this->getVariables());
		return "You have been given the role of {$role}. Log in to explore.";
	}

	/**
	 * @inheritDoc
	 */
	public function getBody(): array
	{
		extract($this->getVariables());

		$url = $this->getDomain();

		return [[
			"bg_colour" => "silent",
			"copy" => [
				"title" => [
					"align" => "left",
					"title" => "You have been given a new role",
					"colour" => "primary",
				],
				"body" => [
					"body" => "{$first_name}, you have been given the role of <b>{$role}</b>. Next time you log in, click on the Account button on the top right of your screen
					to switch between your roles. Different roles will have different access and permissions.",
					"align" => "left"
				]
			],
			"button" => [
				"colour" => "primary",
				"title" => "Log in",
				"url" => $url
			]
		]];
	}
}