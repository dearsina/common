<?php


namespace App\Common\User;

use App\Common\OAuth2\OAuth2Handler;
use App\Common\OAuth2\SingleSignOnTrait;
use App\Common\Prototype;
use App\Common\fields;
use App\Common\str;
use App\UI\Countdown;
use App\UI\Form\Form;
use App\UI\Form\Recaptcha;
use App\UI\Grid;
use App\UI\Icon;
use App\UI\Table;

/**
 * Class Card
 * @package App\Common\User
 */
class Card extends Prototype {
	/**
	 * For use handling user single sign-on.
	 */
	use SingleSignOnTrait;

	public function all(array $a): string
	{
		extract($a);

		$id = str::id("table");

		$body = Table::onDemand([
			"id" => $id,
			"hash" => [
				"rel_table" => $rel_table,
				"action" => "get_" . str::pluralise($rel_table),
				"vars" => $vars,
			],
			"length" => 10,
		]);

		$countdown = Countdown::generate([
			"pre" => "Refreshing in ", //Text that goes before the timer
			"post" => ".",             //Text that goes after the timer
			"vars" => $id,             //Variables to send to the callback function,
			"restart" => [ //Breaks down the time between refreshes
				"minutes" => 10,
			],
		]);

		$card = new \App\UI\Card\Card([
			"header" => [
				"icon" => $icon,
				"title" => str::pluralise(str::title($rel_table)),
				"button" => $button,
			],
			"body" => $body,
			"footer" => true,
			"post" => [
				"class" => "text-center text-muted smaller",
				"html" => $countdown,
			],
		]);

		return $card->getHTML();
	}

	/**
	 * Publicly available card.
	 *
	 * @param array|null $a
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function register(?array $a = NULL)
	{
		extract($a);

		$buttons[] = [
			"colour" => "green",
			"icon" => "save",
			"title" => "Register",
			"type" => "submit",
		];

		$buttons[] = "cancel";

		$form = new Form([
			"action" => "create",
			"rel_table" => "user",
			"callback" => $this->hash->getCallback(),
			"fields" => Field::register($vars),
			"buttons" => $buttons,
		]);

		$card = new \App\UI\Card\Card([
			"header" => "Fill in your details below to register",
			"body" => $form->getHTML(),
			"footer" => [
				"class" => "smaller",
				"html" => "After you have registered you will receive an email with a link to verify your email address.",
			],
			"post" => [
				"class" => "text-muted",
				"style" => [
					"font-size" => "9pt",
					"font-weight" => "300",
				],
				"html" => Recaptcha::getPrivacyPolicy(),
			],
		]);

		return $card->getHTML();
	}

	/**
	 * @param $role
	 *
	 * @return mixed
	 */
	public function selectRole($role)
	{


		return $html;
	}

	/**
	 * @param null $a
	 *
	 * @return string
	 */
	public function verificationEmailSent($a = NULL)
	{
		extract($a);

		if(!$rel_id){
			throw new \Exception("No user ID sent.");
		}

		$user = $this->user->get($rel_id);

		$buttons = [[
			"basic" => true,
			"colour" => "primary",
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
				"action" => "send_verification_email",
			]],
			"buttons" => $buttons,
		]);

		$body = Grid::generate([[
			"html" => "
			Thank you for signing up, {$user['first_name']}!
			",
			"row_style" => [
				"font-weight" => "bolder",
				"margin-bottom" => "1rem",
			],
		], [
			"html" => "To continue, you must verify your email address.
			Check your email, and click on the link in the message from {$_ENV['title']} to verify.
			",
			"row_style" => [
				"margin-bottom" => "1rem",
			],
		], [
			"html" => "
			The message may have landed in your spam or junk folder.
			If you still haven't received anything, try to re-send the message.
			",
		]]);

		$card = new \App\UI\Card\Card([
			//			"draggable" => true,
			"header" => "Verify your email address",
			"body" => $body,
			"footer" => $form->getHTML(),
			"post" => [
				"style" => [
					"font-size" => "9pt",
					"font-weight" => "300",
				],
				"html" => Recaptcha::getPrivacyPolicy(),
			],
		]);

		return $card->getHTML();
	}

	/**
	 * @param array  $a
	 * @param string $narrative
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function newPassword(array $a, string $narrative): string
	{
		if(is_array($a))
			extract($a);

		$buttons = ["save", [
			"colour" => "grey",
			"basic" => true,
			"title" => "Cancel",
			"hash" => [
				"rel_table" => "user",
				"action" => "login",
			],
			"approve" => [
				"title" => "No password?",
				"message" => "If you don't set a password, you will not be able to log in to {$_ENV['title']}.",
				"colour" => "danger",
			],
			"class" => "float-right",
		]];

		$form = new Form([
			"action" => "update_password",
			"rel_table" => $rel_table,
			"rel_id" => $rel_id,
			"callback" => $vars['callback'],
			"fields" => Field::newPassword($vars),
			"buttons" => $buttons,
			"encrypt" => ["new_password", "repeat_new_password"],
		]);

		$card = new \App\UI\Card\Card([
			"header" => "New password",
			"body" => [
				"html" => [
					$narrative,
					$form->getHTML(),
				],
			],
			"footer" => [
				"html" => "Your password must be " . Field::minimumPasswordLength . " characters or longer, contain upper and lower case letters, special characters and numbers.",
				"class" => "smaller",
			],
		]);

		return $card->getHTML();
	}

	/**
	 * @param null $a
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function login($a = NULL)
	{
		if(is_array($a))
			extract($a);

		$buttons = [[
			"colour" => "primary",
			"icon" => "lock",
			"title" => "Log In",
			"type" => "submit",
		], [
			"colour" => "grey",
			"hash" => [
				"rel_table" => "user",
				"action" => "reset_password",
			],
			"basic" => true,
			"title" => "Forgot password",
			"class" => "float-right",
		]];

		// To prevent a loop, remove the callback if it's user//login
		if($this->hash->getCallback() == "user//login"){
			$this->hash->setCallback(false);
		}

		$form = new Form([
			"action" => "verify_credentials",
			"rel_table" => "user",
			"callback" => $this->hash->getCallback(),
			"fields" => Field::login($vars),
			"buttons" => $buttons,
			"encrypt" => ["password"],
		]);

		$card = new \App\UI\Card\Card([
			"header" => [
				"title" => "Sign in to your account",
				"icon" => "sign-in-alt",
			],
			"body" => $form->getHTML(),
			"post" => [
				"class" => "text-center smaller",
				"html" => "Don't have an account yet? <a href=\"/user//register\">Sign up here</a>!",
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
	public function codeFor2FA($a, $user)
	{
		if(is_array($a))
			extract($a);

		$buttons = [[
			"title" => "Cancel",
			"colour" => "grey",
			"basic" => true,
			"hash" => [
				"rel_table" => $rel_table,
				"action" => "login",
			],
		], [
			"colour" => "primary",
			"icon" => "key",
			"title" => "Verify",
			"type" => "submit",
		]];

		$form = new Form([
			"rel_table" => $rel_table,
			"action" => "verify_2FA_code",
			"rel_id" => $user['user_id'],
			"callback" => $this->hash->getCallback(),
			"fields" => Field::codeFor2FA($vars),
			"buttons" => $buttons,
		]);

		$body = [[
			"html" => "
					Welcome, {$user['first_name']}!
					Your account is protected with two-factor authentication.
					Please check your emails for an email with an authentication code.
				",
		], [
			"row_style" => [
				"margin-top" => "1rem",
			],
			"html" => "The email may have landed in your spam or junk folder. If you still haven't received anything, cancel and try to log in again.",
		], [
			"row_style" => [
				"margin-top" => "1rem",
			],
			"html" => $form->getHTML(),
		]];

		$body = ["html" => $body];

		$card = new \App\UI\Card\Card([
			//			"draggable" => true,
			"header" => "Two-factor authentication",
			"body" => $body,
			"post" => [
				"style" => [
					"font-size" => "9pt",
					"font-weight" => "300",
				],
				"html" => "Two-factor authentication can be disabled in your account settings.",
			],
		]);

		return $card->getHTML();
	}

	/**
	 * @param null $a
	 *
	 * @return string
	 */
	public function resetPassword($a = NULL)
	{
		extract($a);

		$buttons = [[
			"colour" => "primary",
			"icon" => "envelope",
			"title" => "Reset password",
			"type" => "submit",
		], "cancel"];

		$form = new Form([
			"action" => "send_reset_password_email",
			"rel_table" => "user",
			"callback" => $this->hash->getCallback(),
			"fields" => Field::resetPassword($vars),
			"buttons" => $buttons,
		]);


		$card = new \App\UI\Card\Card([
			"draggable" => true,
			"header" => "Reset password",
			"body" => $form->getHTML(),
			"post" => [
				"style" => [
					"font-size" => "9pt",
					"font-weight" => "300",
				],
				"html" => Recaptcha::getPrivacyPolicy(),
			],
		]);

		return $card->getHTML();
	}

	/**
	 * @param null $a
	 *
	 * @return string
	 */
	public function newAdmin($a = NULL)
	{
		$card = new \App\UI\Card\Card([
			"header" => [
				"icon" => "exclamation-triangle",
				"title" => "No admins assigned",
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
					],
				],
			],
		]);

		return $card->getHTML();
	}

	private function getPasswordExpiryDateValue(array $user): array
	{
		if($user['password_expiry']){
			if(!User::passwordHasExpired($user)){
				// If the password has yet to expire
				return [
					"html" => str::ago($user['password_expiry'], true),
				];
			}

			// If the password has expired (but the user is still logged in)
			return [
				"html" => Icon::generate([
						"colour" => "warning",
						"name" => "exclamation-triangle",
						"style" => [
							"margin-right" => "0.5rem",
						],
					]) . "Password expired " . str::ago($user['password_expiry']),
				"alt" => "The current password has expired and will need to be changed at the next log in.",
			];
		}

		return [
			"html" => str::check($user['password_expiry'], true, [
				"alt" => "Password expiry has been disabled for this account.\r\nThe current password will never expire.",
				"style" => ["margin" => ".3rem 0 -.3rem 0"],
			]),
		];
	}

	private function getTwoFactorAuthenticationValue(array $user): string
	{
		if($user['2fa_enabled']){
			return str::check($user['2fa_enabled'], true, [
				"alt" => "Two factor authentication is enabled",
				"style" => ["margin" => ".3rem 0 -.3rem 0"],
			]);
		}

		return str::check($user['2fa_enabled'], true, [
			"alt" => "Two-factor authentication has been disabled for this account.",
			"style" => ["margin" => ".3rem 0 -.3rem 0"],
		]);
	}

	/**
	 * @param $user
	 *
	 * @return string
	 */
	public function user(array $user): string
	{
		$rows = [
			"First Name" => $user['first_name'],
			"Last Name" => $user['last_name'],
			"Phone" => $user['phone'],
			"Email" => [
				"html" => $user['email'],
				"copy" => true,
			],
			"Verified" => str::check($user['verified'], true, [
				"alt" => "The email address is " . ($user['verified'] ? "verified" : "not verified yet"),
				"style" => ["margin" => ".3rem 0 -.3rem 0"],
			]),
			"Two-factor authentication" => $this->getTwoFactorAuthenticationValue($user),
			"Password expiry date" => $this->getPasswordExpiryDateValue($user),
		];

		$card = new \App\UI\Card\Card([
			"header" => [
				"title" => "Account",
				"buttons" => $this->getAccountButtons($user),
			],
			"rows" => [
				"sm" => 3,
				"rows" => $rows,
			],
		]);

		return $card->getHTML();
	}

	/**
	 * SSO card in the account settings page for a user.
	 *
	 * @param array $user
	 *
	 * @return string
	 * @throws \App\Common\Exception\BadRequest
	 */
	public function sso(array $user): string
	{
		if($user['sso_id']){
			$oauth_token = $this->info("oauth_token", $user['oauth_token_id']);
			$provider = OAuth2Handler::getSingleSignOnProviderObject($oauth_token['provider'], $oauth_token);
			$org = $provider->getOrganization($oauth_token);
			$me = $provider->getUser($user['sso_id']);

			$rows['First name'] = $me['first_name'];
			$rows['Last name'] = $me['last_name'];
			$rows['Phone'] = $me['phone'];
			$rows['Email'] = $me['email'];
			$rows["Organization"] = $org['name'];
			$rows["Job title"] = $me['job_title'];
			$rows["Office location"] = $me['office_location'];

			switch($oauth_token['provider']) {
			case "entra":
				$rows['Provider'] = "Entra ID (Azure Active Directory)";
				$icon = [
					"svg" => "/img/EntraLogoColour.svg",
					"style" => [
						"margin-right" => "0.5rem"
					],
					"tooltip" => $rows['Provider'],
				];
				break;
			}

			$card = new \App\UI\Card\Card([
				"header" => [
					"icon" => $icon,
					"title" => "Single sign-on",
					"buttons" => [
						Buttons::disconnectSso($user)
					]
				],
				"rows" => [
					"sm" => 3,
					"rows" => $rows,
				],
			]);
		}

		else {
			$card = new \App\UI\Card\Card([
				"header" => [
					"title" => "Single sign-on",
				],
				"body" => [
					"style" => [
						"text-align" => "center",
					],
					"html" => Card::getSignOnButton("entra", NULL, $user['user_id']),
				]
			]);
		}



		return $card->getHTML();
	}

	private function getAccountButtons(array $user): array
	{
		$header_buttons[] = Buttons::edit($user);
		$header_buttons[] = Buttons::editEmail($user);
		$header_buttons[] = Buttons::updatePassword($user);

		if(!$user['verified'] || !$user['password']){
			$header_buttons[] = Buttons::sendVerificationEmail($user);
		}

		$header_buttons[] = Buttons::toggleTwoFactorAuthentication($user);
		$header_buttons[] = Buttons::togglePasswordExpiry($user);
		$header_buttons[] = Buttons::close($user);

		return $header_buttons;
	}
}