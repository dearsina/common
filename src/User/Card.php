<?php


namespace App\Common\User;

use App\Common\Common;
use App\Common\fields;
use App\Common\str;
use App\UI\Form\Form;
use App\UI\Form\Recaptcha;
use App\UI\Grid;

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
	public function selectRole($role){


		return $html;
	}
	public function verificationEmailSent($a = NULL){
		extract($a);

		$user = $this->sql->select([
			"table" => $rel_table,
			"id" => $rel_id,
		]);

		$buttons = [[
			"colour" => "green",
			"icon" => "envelope",
			"title" => "Resend",
			"type" => "submit",
		]];

		$form = new Form([
			"action" => "send_verification_email",
			"rel_table" => "user",
			"rel_id" => $rel_id,
			"callback" => $this->hash->getCallback(),
			"fields" => [[
				"type" => "recaptcha",
				"action" => "send_verification_email"
			]],
			"buttons" => $buttons
		]);

		$body = Grid::generate([[
			"html" => "
			Thank you for registering, {$user['first_name']}!
			To continue, you must verify your email address.
			",
			"style" => [
				"font-weight" => "bolder",
				"margin-bottom" => "1rem"
			]
		],[
			"html" => "
			Check your email, and click on the link in the message from {$_ENV['title']} to verify.
			"
		],[
			"html" => "
			The message may have landed in your spam or junk folder.
			If you still haven't received anything, try to re-send the message.
			"
		]]);

		$card = new \App\UI\Card([
			"draggable" => true,
			"header" => "Verify your email address",
			"body" => $body,
			"footer" => $form->getHTML(),
			"post" => [
				"class" => "small",
				"html" => Recaptcha::getPrivacyPolicy()
			]
		]);

		$html = $card->getHTML();

		return $html;
	}
	public function newPassword($a = NULL){
		if(is_array($a))
			extract($a);

		$buttons = ["save",[
			"colour" => "grey",
			"basic" => true,
			"title" => "Cancel",
			"hash" => [
				"rel_table" => "user",
				"action" => "login"
			],
			"approve" => [
				"title" => "No password?",
				"message" => "If you don't set a password, you will not be able to log in to {$_ENV['title']}.",
				"colour" => "danger",
			]
		]];

		$form = new Form([
			"action" => "update_password",
			"rel_table" => $rel_table,
			"rel_id" => $rel_id,
			"fields" => Field::newPassword($vars),
			"buttons" => $buttons
		]);

		$card = new \App\UI\Card([
			"header" => "Thank you for verifying your email address",
			"body" => $form->getHTML(),
			"footer" => [
				"html" => "Your password must be ".Field::minimumPasswordLength." characters or longer.",
				"class" => "smaller",
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
			"callback" => $this->hash->getCallback() == "user//login" ? false : $this->hash->getCallback(),
			// This is to prevent a loop
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
//				"html" => str::pre(print_r($_COOKIE, true))
			],
		]);

		$html = $card->getHTML();

		return $html;
	}

	public function codeFor2FA($a, $user){
		if(is_array($a))
			extract($a);

		$buttons = [[
			"colour" => "primary",
			"icon" => "key",
			"title" => "Verify",
			"type" => "submit",
		],[
			"title" => "Cancel",
			"colour" => "grey",
			"basic" => true,
			"hash" => [
				"rel_table" => "rel_table",
				"action" => "login"
			]
		]];

		$form = new Form([
			"action" => "verify_2FA_code",
			"rel_id" => $user['user_id'],
			"callback" => $this->hash->getCallback(),
			"fields" => Field::codeFor2FA($vars),
			"buttons" => $buttons
		]);

		$card = new \App\UI\Card([
			"draggable" => true,
			"header" => "Two-factor authentication",
			"body" => [[
				"html" => "
					Welcome, {$user['first_name']}!
					Your account has enabled two factor authentication.
					Please check your emails for an email with a two-factor authentication code.
				"
			],[
				"html" => "The email may have landed in your spam or junk folder. If you still haven't received anything, try to re-log in."
			],[
				"html" => $form->getHTML()
			]],
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
			"callback" => $this->hash->getCallback(),
			"fields" => Field::resetPassword($vars),
			"buttons" => $buttons
		]);


		$card = new \App\UI\Card([
			"draggable" => true,
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

	public function newAdmin($a = NULL){
		$card = new \App\UI\Card([
			"header" => [
				"icon" => "exclamation-triangle",
				"title" => "No admins assigned"
			],
			"body" => "No admins have been assigned for this application. The app needs admins to manage it. Click on the buttom below to set this user as the first.",
			"footer" => [
				"button" => [
					"type" => "submit",
					"colour" => "red",
					"icon" => "user-plus",
					"title" => "Create admin",
					"hash" => [
						"action" => "insert",
						"rel_table" => "admin",
					]
				]
			]
		]);

		$html = $card->getHTML();

		return $html;
	}
}