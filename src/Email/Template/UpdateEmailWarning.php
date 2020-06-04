<?php


namespace App\Common\Email\Template;


use App\Common\Email\TemplateConstructor;
use App\Common\Email\TemplateInterface;
use App\UI\Grid;

/**
 * Class UpdateEmailWarning
 * @package App\Common\Email\Template
 */
class UpdateEmailWarning extends TemplateConstructor implements TemplateInterface {

	/**
	 * @inheritDoc
	 */
	public function getSubject (): string
	{
		return "Email address change request";
	}

	/**
	 * @inheritDoc
	 */
	public function getMessage (): string
	{
		extract($this->variables);

		$grid = new Grid();
		$grid->set([
			"html" => "This is a courtesy notice to inform you that a request to change your email address from {$email} to {$new_email} has been received."
		]);
		$grid->set([
			"html" => "If this was not you, please notify us immediately."
		]);
		return $grid->getHTML();
	}
}