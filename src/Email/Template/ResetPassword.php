<?php


namespace App\Common\Email\Template;


use App\Common\Email\TemplateConstructor;
use App\Common\Email\TemplateInterface;
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
class ResetPassword extends TemplateConstructor implements TemplateInterface {

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
	public function getMessage() : string
	{
		extract($this->variables);
		
		$grid = new Grid();
		$grid->set([
			"style" => [
				"font-weight" => "bold"
			],
			"html" => "Reset your password for {$_ENV['title']}."
		]);
		$grid->set([
			"html" => "To reset your password, please click on the link below:"
		]);
		$url  = "https://{$_SERVER['HTTP_HOST']}/";
		$url .= str::generate_uri([
			"rel_table" => "user",
			"rel_id" => $user_id,
			"action" => "new_password",
			"vars" => [
				"key" => $key
			]
		]);

		$grid->set([
			"html" => "<a href=\"{$url}\">{$url}</a>"
		]);
		return $grid->getHTML();
	}
}