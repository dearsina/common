<?php


namespace App\Common\Email\Template;


use App\Common\str;
use App\UI\Grid;

class Email2FACode extends \App\Common\Email\TemplateConstructor implements \App\Common\Email\TemplateInterface {

	/**
	 * @inheritDoc
	 */
	public function getSubject (): string
	{
		return "{$_ENV['title']} two-factor authentication code";
	}

	/**
	 * @inheritDoc
	 */
	public function getMessage (): string
	{
		extract($this->variables);

		$grid = new Grid();
		$grid->set([
			"html" => "Your two-factor authentication code for {$_ENV['title']} is:"
		]);
		$grid->set([
			"style" => [
				"font-size" => "x-large",
				"letter-spacing" => "1rem"
			],
			"html" => $code
		]);
		$grid->set([
			"html" => "The code is case <i>in</i>sensitive and will expire in 15 minutes."
		]);
		return $grid->getHTML();
	}
}