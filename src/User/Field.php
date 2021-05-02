<?php


namespace App\Common\User;


use App\Common\href;
use App\UI\Countdown;

/**
 * Class Field
 * @package App\Common\User
 */
class Field {
	/**
	 * Minimum password length
	 */
	const minimumPasswordLength = 8;

	/**
	 * @param null $a
	 *
	 * @return array[]
	 */
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

	/**
	 * Externally facing.
	 *
	 * @param null $a
	 *
	 * @return array
	 */
	public static function register($a = NULL) {
		if (is_array($a))
			extract($a);

		return [[[
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
			"value" => urldecode($first_name),
			"sm" => 5
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
			"value" => urldecode($last_name),
			"sm" => 7
		]],[
//			"name" => "company_name",
//			"autocomplete" => "company",
//			"label" => false,
//			"placeholder" => "Company Name",
//			"value" => $company_name,
//		],[
			"type" => "email",
			"autocomplete" => "email",
			"name" => "email",
			"icon" => "envelope",
			"label" => false,
			"placeholder" => "Email address",
			"validation" => [
				"required" => [
					"rule" => true,
					"msg" => "Please ensure you've entered a valid email address."
				],
			],
			"value" => $email
		],[
			"type" => "tel",
			"autocomplete" => "tel",
			"name" => "phone",
			"placeholder" => false,
			"validation" => [
				"tel" => [
					"rule" => true,
					"msg" => "Please ensure you enter your full mobile number."
				],
			],
			"label" => false,
			"value" => $phone,
			"required" => true,
		],[
			"type" => "recaptcha",
			"action" => "insert_user"
		],[
			"type" => "checkbox",
			"name" => "tnc",
			"checked" => $tnc,
			"label" => href::a([
					"pre" => "I accept the ",
					"html" => "Terms and Conditions",
					"post" => ".",
					"hash" => [
						"rel_table" => "narrative",
						"rel_id" => "termsconditions"
					]
				]),
			"validation" => [
				"required" => [
					"rule" => true,
					"msg" => "Please make sure you read and accept our terms and conditions."
				],
			],
			"value" => $tnc
		]];
	}

	/**
	 * Internal only.
	 *
	 * @param null $a
	 *
	 * @return array
	 */
	public static function new($a = NULL) {
		if (is_array($a))
			extract($a);

		return [[[
			"name" => "first_name",
			"autocomplete" => "name given-name",
			"label" => false,
			"placeholder" => "First name",
			"required" => true,
			"value" => $first_name,
			"sm" => 5
		],[
			"name" => "last_name",
			"autocomplete" => "name family-name",
			"label" => false,
			"placeholder" => "Last name",
			"required" => true,
			"value" => $last_name,
			"sm" => 7
		]],[
//			"name" => "company_name",
//			"autocomplete" => "company",
//			"label" => false,
//			"placeholder" => "Company Name",
//			"value" => $company_name,
//		],[
			"type" => "email",
			"autocomplete" => "email",
			"name" => "email",
			"icon" => "envelope",
			"label" => false,
			"placeholder" => "Email address",
			"required" => true,
			"value" => $email
		],[
			"type" => "tel",
			"autocomplete" => "tel",
			"name" => "phone",
			"placeholder" => false,
			"validation" => [
				"tel" => [
					"rule" => true,
					"msg" => "Please ensure you enter a full mobile number."
				],
			],
			"label" => false,
			"value" => $phone
		],[
			"type" => "checkbox",
			"name" => "welcome_email",
			"checked" => false,
			"label" => [
				"title" => "Send welcome email",
				"desc" => "Sends a welcome email to the new user with a link to verify their account and set up a password."
			],
		]];
	}

	/**
	 * @param null $a
	 *
	 * @return array[]
	 */
	public static function edit($a = NULL, ?bool $elevated = NULL) {
		if (is_array($a))
			extract($a);

		if($elevated){
			$desc = "Email address changes to verified accounts will result in emails going to the user's old and new addresses for verification.";
		} else {
			$disabled = true;
			$desc = "There is a separate process for changing your email address.";
		}

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
			"icon" => "envelope",
			"label" => false,
			"placeholder" => "Email address",
			"disabled" => $disabled,
			"desc" => $desc,
			"value" => $email,
			"name" => "email",
			"required" => true,
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
		]];
	}

	/**
	 * @param null $a
	 *
	 * @return array|array[]
	 */
	public static function editEmail($a = NULL): array
	{
		if (is_array($a))
			extract($a);

		return [[
			"html" => "<p>If you wish to update your email address, 
			enter the new one here along with your current password. 
			An email will be sent to your new address with a verification 
			link to complete the update process.</p>
			<p>Until you complete the process, please continue to use
			your current address to log in.</p>",
		],[
			"type" => "email",
			"autocomplete" => "off",
			"name" => "new_email",
			"icon" => "envelope",
			"label" => false,
			"placeholder" => "New email address",
			"validation" => [
				"required" => [
					"rule" => true,
					"msg" => "Please ensure you've entered a valid email address."
				],
			],
		],[
			"icon" => "key",
			"type" => "password",
			"name" => "password",
			"placeholder" => "Current password",
			"label" => false,
			"required" => "For added security, please enter your current password.",
			"autocomplete" => "new-password"
		]];
	}

	/**
	 * @param null $a
	 *
	 * @return array
	 */
	public static function newPassword($a = NULL) {
		if (is_array($a))
			extract($a);

		return [[
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

	/**
	 * @param null $a
	 *
	 * @return array
	 */
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

	/**
	 * @param null $a
	 *
	 * @return array[]
	 * @throws \Exception
	 */
	public static function codeFor2FA($a = NULL)
	{
		if (is_array($a))
			extract($a);

		return [[
			"name" => "code",
			"label" => false,
			"placeholder" => "Enter code here",
			"required" => true,
			"validation" => [
				"minLength" => [
					"rule" => 4,
					"msg" => "Your code doesn't seem to be complete"
				]
			],
			"desc" => Countdown::generate([
				"modify" => "+15 minutes",
				"stop" => "Your code has expired, please cancel and log in again.",
				"pre" => "The code is not case sensitive and will expire in ",
				"post" => ".",
				"callback" => false
			]),
			"autocomplete" => "off"
		],[
			"type" => "hidden",
			"name" => "remember",
			"value" => $remember
		]];
	}
}