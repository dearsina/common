<?php


namespace App\Common\User;


class Field {
	/**
	 * Minimum password length
	 */
	const minimumPasswordLength = 8;

	public static function login($a = NULL){
		if(is_array($a))
			extract($a);

		return [[
			"type" => "email",
			"name" => "email",
			"label" => false,
			"value" => $email,
			"required" => "Please enter your email address.",
			"icon" => "user"
		],[
			"icon" => "key",
			"type" => "password",
			"name" => "password",
			"label" => false,
			"required" => "If you have forgotten your password, click on the forgot password button.",
			"value" => $password,
		],[
			"type" => "checkbox",
			"name" => "remember",
			"label" => "Remember me",
			"value" => 1,
			"checked" => $remember
		]];
	}

	public static function new($a = NULL) {
		if (is_array($a))
			extract($a);

		return [[
			"name" => "first_name",
			"autocomplete" => "name given-name",
			"label" => false,
			"placeholder" => "First name",
			"validation" => [
				"required" => [
					"rule" => true,
					"msg" => "Please ensure you have written your first name in full."
				],
				"minLength" => 2
			],
			"value" => $first_name
		],[
			"name" => "last_name",
			"autocomplete" => "name family-name",
			"label" => false,
			"placeholder" => "Last name",
			"validation" => [
				"required" => [
					"rule" => true,
					"msg" => "Please ensure you have written your last name in full."
				],
				"minLength" => 2
			],
			"value" => $last_name
		],[
			"type" => "email",
			"autocomplete" => "email",
			"name" => "email",
			"icon" => "envelope",
			"label" => false,
			"placeholder" => "Email address",
			"validation" => [
				"required" => [
					"rule" => true,
					"msg" => "Please ensure a valid email address."
				],
			],
			"value" => $email
		],[
			"type" => "tel",
			"autocomplete" => "tel",
			"name" => "phone",
			"placeholder" => false,
//			"icon" => "phone",
			"validation" => [
				"tel" => [
					"rule" => true,
					"msg" => "Please ensure you enter your full mobile number."
				],
			],
			"label" => false,
			"value" => $phone
		],[
			"type" => "recaptcha",
			"action" => "insert_user"
		],[
			"type" => "checkbox",
			"name" => "tnc",
			"checked" => $tnc,
			"label" => "I accept the <a href=\"#terms_and_conditions\">Terms and Conditions</a>",

			"validation" => [
				"required" => [
					"rule" => true,
					"msg" => "Please make sure you read and accept our terms and conditions."
				],
			],
			"value" => $tnc
		]];

	}

	public static function newPassword($a = NULL) {
		if (is_array($a))
			extract($a);

		return [[
			"html" => "<p>Please enter a new password for your account.</p>",
		],[
			"type" => "password",
			"icon" => "key",
			"name" => "new_password",
			"label" => "New password",
			"required" => true,
			"validation" => [
				"minlength" => [
					"rule" => self::minimumPasswordLength,
					"msg" => "Your password must be at least {0} characters."
				]
			]
		],[
			"type" => "password",
			"icon" => "key",
			"name" => "repeat_new_password",
			"label" => "Repeat new password",
			"validation" => [
				"equalTo" => [
					"rule" => "input[name=new_password]",
					"msg" => "Repeated password does not match."
				]
			],
		],[
			"type" => "hidden",
			"name" => "key",
			"value" => $key
		]];
	}

	public static function resetPassword($a = NULL) {
		if (is_array($a))
			extract($a);

		return [[
			"html" => "<p>Enter the email address you used to register with. If the address is valid, an email will be sent to you with a link to reset your password.</p>",
		],[
			"type" => "email",
			"name" => "email",
			"label" => false,
			"required" => true,
			"value" => $email
		],[
			"type" => "recaptcha",
			"action" => "reset_password"
		]];
	}


}