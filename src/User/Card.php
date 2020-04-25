<?php


namespace App\Common\User;


use App\Common\Common;
use App\UI\Form\Form;
use App\UI\Form\Recaptcha;

class Card extends Common {
	public function new($a = NULL){
		extract($a);

		$buttons = [[
			"colour" => "green",
			"icon" => "save",
			"title" => "Register",
			"type" => "submit"
		],"cancel"];

		$form = new Form([
			"action" => "insert",
			"rel_table" => "user",
			"rel_id" => NULL,
			"callback" => $this->hash->getCallback(),
			"fields" => Field::new($vars),
			"buttons" => $buttons
		]);

		$card = new \App\UI\Card([
			"header" => "Fill in the details below to register.",
			"body" => $form->getHTML(),
			"footer" => "After you have registered you will receive an email with a link to verify your email address.",
			"post" => [
				"class" => "small",
				"html" => Recaptcha::getPrivacyPolicy()
			]
		]);

		$html = $card->getHTML();

		return $html;
	}
	public function login($a = NULL){
		if(is_array($a))
			extract($a);

		$buttons = [[
			"colour" => "primary",
			"icon" => "lock",
			"title" => "Log In",
			"type" => "submit",
		],[
			"colour" => "grey",
			"hash" => [
				"rel_table" => "user",
				"action" => "reset_password"
			],
			"basic" => true,
			"title" => "Forgot password",
		]];

		$form = new Form([
			"action" => "verify_credentials",
			"rel_table" => "user",
			"rel_id" => NULL,
			"callback" => $this->hash->getCallback(),
			"fields" => Field::login($vars),
			"buttons" => $buttons
		]);

		$card = new \App\UI\Card([
			"header" => [
				"title" => "Sign in to your account",
				"icon" => "sign-in-alt",
			],
			"body" => $form->getHTML(),
			"post" => [
				"class" => "text-center smaller",
				"html" => "Don't have an account yet? <a href=\"/user//new\">Sign up here</a>!",
			]
		]);

		$html = $card->getHTML();

		return $html;
	}

	public function resetPassword($a = NULL){
		extract($a);

		$buttons = [[
			"colour" => "primary",
			"icon" => "envelope",
			"title" => "Reset password",
			"type" => "submit"
		],"cancel"];

		$form = new Form([
			"action" => "send_reset_password_email",
			"rel_table" => "user",
			"rel_id" => NULL,
			"callback" => $this->hash->getCallback(),
			"fields" => Field::resetPassword($vars),
			"buttons" => $buttons
		]);


		$card = new \App\UI\Card([
			"header" => "Reset password",
			"body" => $form->getHTML(),
			"post" => [
				"class" => "small",
				"html" => Recaptcha::getPrivacyPolicy()
			]
		]);

		$html = $card->getHTML();

		return $html;
	}
}