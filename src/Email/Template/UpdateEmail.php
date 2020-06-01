<?php


namespace App\Common\Email\Template;


use App\Common\str;
use App\UI\Grid;

class UpdateEmail extends \App\Common\Email\TemplateConstructor implements \App\Common\Email\TemplateInterface {

	/**
	 * @inheritDoc
	 */
	public function getSubject (): string
	{
		return "Change of email address";
	}

	/**
	 * @inheritDoc
	 */
	public function getMessage (): string
	{
		extract($this->variables);

		$grid = new Grid();
		$grid->set([
			"style" => [
				"font-weight" => "bold",
			],
			"html" => "Updating your email address",
		]);
		$grid->set([
			"html" => "
				Follow the link below to update your email address to {$new_email}:
			",
		]);
		$url = "https://{$_SERVER['HTTP_HOST']}/";
		$url .= str::generate_uri([
			"rel_table" => "user",
			"rel_id" => $user_id,
			"action" => "update_email",
			"vars" => [
				"new_email" => $new_email,
				"checksum" => $checksum,
			],
		]);
		$grid->set([
			"html" => "<a href=\"{$url}\">{$url}</a>",
		]);
		return $grid->getHTML();
	}
}