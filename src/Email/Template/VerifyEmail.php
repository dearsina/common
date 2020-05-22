<?php


namespace App\Common\Email\Template;

use App\Common\Email\TemplateConstructor;
use App\Common\Email\TemplateInterface;
use App\Common\str;
use App\UI\Grid;

/**
 * Class VerifyEmail
 *
 * Email sent to anyone who registers, to verify
 * their email address.
 *
 * @package App\Common\Email\Template
 */
class VerifyEmail extends TemplateConstructor implements TemplateInterface {

	/**
	 * @inheritDoc
	 */
	public function getSubject (): string
	{
		return "Welcome to {$_ENV['title']}";
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
			"html" => "Welcome to {$_ENV['title']}!",
		]);
		$grid->set([
			"html" => "
				Thank you for registering with {$_ENV['title']}!<br/>
				To complete your registration, please verify your email
				address by clicking on the link below:
			",
		]);
		$url = "https://{$_SERVER['HTTP_HOST']}";
		$url .= str::generate_uri([
			"rel_table" => "user",
			"rel_id" => $user_id,
			"action" => "verify_email",
			"vars" => [
				"email" => $email,
				"key" => $key,
			],
		]);
		$grid->set([
			"html" => "<a href=\"{$url}\">{$url}</a>",
		]);
		return $grid->getHTML();
	}
}