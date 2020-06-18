<?php


namespace App\Common\User;

use App\Common\Common;
use App\Common\fields;
use App\Common\str;
use App\UI\Form\Form;
use App\UI\Form\Recaptcha;
use App\UI\Grid;
use App\UI\Icon;

/**
 * Class Card
 * @package App\Common\User
 */
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

		return $card->getHTML();
	}

	/**
	 * @param $role
	 *
	 * @return mixed
	 */
	public function selectRole($role){


		return $html;
	}

	/**
	 * @param null $a
	 *
	 * @return string
	 */
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

		return $card->getHTML();
	}

	/**
	 * @param null $a
	 *
	 * @return string
	 */
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
				"message" => "If you don't set a password, you will not be able to alert in to {$_ENV['title']}.",
				"colour" => "danger",
			]
		]];

		$form = new Form([
			"action" => "update_password",
			"rel_table" => $rel_table,
			"rel_id" => $rel_id,
			"fields" => Field::newPassword($vars),
			"buttons" => $buttons,
			"encrypt" => ["new_password", "repeat_new_password"]
		]);

		$card = new \App\UI\Card([
			"header" => "Thank you for verifying your email address",
			"body" => $form->getHTML(),
			"footer" => [
				"html" => "Your password must be ".Field::minimumPasswordLength." characters or longer.",
				"class" => "smaller",
			]
		]);

		return $card->getHTML();
	}

	/**
	 * @param null $a
	 *
	 * @return string
	 */
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
			"buttons" => $buttons,
			"encrypt" => ["password"],
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

		return $card->getHTML();
	}

	/**
	 * @param $a
	 * @param $user
	 *
	 * @return string
	 */
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
				"rel_table" => $rel_table,
				"action" => "login"
			]
		]];

		$form = new Form([
			"rel_table" => $rel_table,
			"action" => "verify_2FA_code",
			"rel_id" => $user['user_id'],
			"callback" => $this->hash->getCallback(),
			"fields" => Field::codeFor2FA($vars),
			"buttons" => $buttons
		]);

		$body = [[
			"html" => "
					Welcome, {$user['first_name']}!
					Your account is protected with two-factor authentication.
					Please check your emails for an email with an authentication code.
				"
		],[
			"row_style" => [
				"margin-top" => "1rem"
			],
			"html" => "The email may have landed in your spam or junk folder. If you still haven't received anything, try to re-alert in."
		],[
			"row_style" => [
				"margin-top" => "1rem"
			],
			"html" => $form->getHTML(),
		]];

		$body = ["html" => $body];

		$card = new \App\UI\Card([
//			"draggable" => true,
			"header" => "Two-factor authentication",
			"body" => $body,
			"footer" => [
				"class" => "small text-silent",
				"html" => "Two-factor authentication can be disabled in your account settings."
			]
		]);

		return $card->getHTML();
	}

	/**
	 * @param null $a
	 *
	 * @return string
	 */
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

		return $card->getHTML();
	}

	/**
	 * @param null $a
	 *
	 * @return string
	 */
	public function newAdmin($a = NULL){
		$card = new \App\UI\Card([
			"header" => [
				"icon" => "exclamation-triangle",
				"title" => "No admins assigned"
			],
			"body" => "No admins have been assigned for this application. The app needs admins to manage it. Click on the button below to set this user as the first.",
			"footer" => [
				"button" => [
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

		return $card->getHTML();
	}

	/**
	 * @param $user
	 *
	 * @return string
	 */
	public function user($user) : string
	{
		$rows = [
			"First Name" => $user['first_name'],
			"Last Name" => $user['last_name'],
			"Phone" => $user['phone'],
			"Email" => [
				"html" => $user['email'],
				"copy" => true
			],
			"Two-factor authentication" => str::check($user['2fa_enabled'], true, [
				"alt" => "Two factor authentication is " . ($user['2fa_enabled'] ? "enabled" : "disabled"),
				"style" => ["margin" => ".3rem 0 -.3rem 0"]
			])
		];

		$header_buttons[] = [
			"hash" => [
				"rel_table" => "user",
				"rel_id" => $user['user_id'],
				"action" => "edit",
				"vars" => [
					"callback" => str::generate_uri([
						"rel_table" => "user",
						"rel_id" => $user['user_id']
					], true)
				]
			],
			"title" => "Edit...",
			"icon" => Icon::get("edit")
		];

		$header_buttons[] = [
			"hash" => [
				"rel_table" => "user",
				"rel_id" => $user['user_id'],
				"action" => "edit_email",
			],
			"title" => "Edit email address...",
			"icon" => "envelope",
		];

		if($user['2fa_enabled']){
			$title = "Disable two-factor authentication...";
			$approve = [
				"icon" => Icon::get("2fa"),
				"colour" => "warning",
				"title" => "Disable two-factor authentication?",
				"message" => "Your account is more secure when you need a password and a verification code to sign in. If you remove this extra layer of security, you will only be asked for a password when you sign in. It might be easier for someone to break into your account."
			];
		} else {
			$title = "Enable two-factor authentication...";
			$approve = false;
		}
		$header_buttons[] = [
			"hash" => [
				"rel_table" => "user",
				"rel_id" => $user['user_id'],
				"action" => "toggle_2FA",
			],
			"title" => $title,
			"approve" => $approve,
			"icon" => Icon::get("2fa"),
		];

		$header_buttons[] = [
			"hash" => [
				"rel_table" => "user",
				"rel_id" => $user['user_id'],
				"action" => "remove",
				"variables" => [
					"callback" => "logout"
				]
			],
			"title" => "Close account...",
			"icon" => "times",
			"approve" => [
				"colour" => "red",
				"title" => "Close your account",
				"message" => "Are you sure you want to close your account? All your data will be removed immediately. This cannot be undone."
			]
		];

		$card = new \App\UI\Card([
			"header" => [
				"title" => "Account",
				"buttons" => $header_buttons
			],
			"rows" => [
				"sm" => 3,
				"rows" => $rows
			]
		]);

		return $card->getHTML();
	}
}