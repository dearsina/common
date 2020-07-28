<?php


namespace App\Common\Template;


use App\UI\Grid;

/**
 * Class Email2FACode
 * @package App\Common\Email\Template
 */
class Email2FACode extends Template implements TemplateInterface {

	/**
	 * @inheritDoc
	 */
	public function getSubject(): string
	{
		return "{$_ENV['title']} two-factor authentication code";
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
					"title" => "Two-factor authentication",
					"colour" => "primary",
				],
				"body" => [
					"body" => "Your two-factor authentication code for {$_ENV['title']} is:",
					"align" => "left",
				],
			],
		], [
			"bg_colour" => "silent",
			"copy" => [
				"title" => [
					"colour" => "primary",
					"title" => $code,
					"style" => [
						"font-size" => "48pt",
						"letter-spacing" => "1rem",
						"font-weight" => "bold",
						"padding" => "0 0 50px 0"
					],
				],
				"body" => [
					"colour" => "grey",
					"body" => "The code is not case sensitive and will expire in 15 minutes.",
					"align" => "left",
				],
			],
		]];


		//		$grid = new Grid();
		//		$grid->set([
		//			"html" => "Your two-factor authentication code for {$_ENV['title']} is:"
		//		]);
		//		$grid->set([
		//			"style" => [
		//				"font-size" => "x-large",
		//				"letter-spacing" => "1rem"
		//			],
		//			"html" => $code
		//		]);
		//		$grid->set([
		//			"html" => "The code is not case sensitive and will expire in 15 minutes."
		//		]);
		//		return $grid->getHTML();
	}

	/**
	 * The preheader is single line of text that is presented to the user when browsing their inbox,
	 * right underneath the subject line. Useful to give context to what the email contains.
	 * @return string
	 */
	public function getPreheader(): ?string
	{
		return NULL;
	}
}