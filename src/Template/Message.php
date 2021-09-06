<?php

namespace App\Common\Template;

use App\Common\str;

class Message extends Template implements TemplateInterface {

	/**
	 * @inheritDoc
	 */
	public function getSubject(): string
	{
		extract($this->getVariables());
		return $subject ?: "A note from the system";
	}

	/**
	 * @inheritDoc
	 */
	public function getPreheader(): ?string
	{
		return NULL;
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
					"title" => $subject,
					"colour" => "primary",
				],
				"body" => [
					"body" => $body,
					"align" => "left"
				]
			],
		],[
			"bg_colour" => "silent",
			"copy" => [
				"body" => [
					"colour" => "grey",
					"style" => [
						"font-size" => "8pt",
						"font-family" => "'Lucida Console', monospace"
					],
					"body" => [
						str::newline($backtrace),
					],
					"align" => "left"
				]
			],
		]];
	}
}