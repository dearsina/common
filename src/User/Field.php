<?php


namespace App\Common\User;


use App\Common\href;
use App\Common\Prototype\FieldPrototype;
use App\Common\RemoteStorage\RemoteStorage;
use App\Language\Language;
use App\UI\Countdown;

/**
 * Class Field
 * @package App\Common\User
 */
class Field extends FieldPrototype {
	/**
	 * Minimum password length
	 */
	const minimumPasswordLength = 8;

	/**
	 * @param null $a
	 *
	 * @return array[]
	 */
	public static function login($a = NULL)
	{
		if(is_array($a))
			extract($a);

		return [[
			"type" => "email",
			"name" => "email",
			"label" => false,
			"value" => $email,
			"required" => "Please enter your email address.",
			"icon" => "user",
		], [
			"icon" => "key",
			"type" => "password",
			"name" => "password",
			"label" => false,
			"required" => "If you have forgotten your password, click on the forgot password button.",
			"value" => $password,
		], [
			"type" => "checkbox",
			"name" => "remember",
			"label" => "Remember me",
			"value" => 1,
			"checked" => $remember,
		]];
	}

	private static function fieldCurrentPassword(): array
	{
		return [
			"icon" => "key",
			"placeholder" => "Current password",
			"type" => "password",
			"name" => "password",
			"label" => [
				"title" => "Current password",
			],
			"autocomplete" => "current-password",
			"required" => true
		];
	}

	private static function fieldNewPassword(): array
	{
		return [
			"placeholder" => "New password",
			"type" => "password",
			"icon" => "key",
			"name" => "new_password",
			"autocomplete" => "new-password",
			"validation" => [
				"password" => [
					"rule" => [
						"min_length" => self::minimumPasswordLength,
						"uppercase" => 1,
						"lowercase" => 1,
						"special" => 1,
						"number" => 1,
					],
				],
			],
			"required" => true,
		];
	}

	private static function fieldRepeatNewPassword(): array
	{
		return [
			"placeholder" => "Repeat new password",
			"type" => "password",
			"icon" => "key",
			"name" => "repeat_new_password",
			"autocomplete" => "new-password",
			"validation" => [
				"equalTo" => [
					"rule" => "input[name=new_password]",
					"msg" => "Repeated password does not match.",
				],
			],
			"required" => true,
		];
	}

	/**
	 * Externally facing.
	 *
	 * @param null $a
	 *
	 * @return array
	 */
	public static function register($a = NULL)
	{
		if(is_array($a))
			extract($a);

		return [[[
			"name" => "first_name",
			"autocomplete" => "name given-name",
			"label" => false,
			"placeholder" => "First name",
			"validation" => [
				"required" => [
					"rule" => true,
					"msg" => "Please ensure you have written your first name in full.",
				],
				"minLength" => 2,
			],
			"value" => urldecode($first_name),
			"sm" => 5,
		], [
			"name" => "last_name",
			"autocomplete" => "name family-name",
			"label" => false,
			"placeholder" => "Last name",
			"validation" => [
				"required" => [
					"rule" => true,
					"msg" => "Please ensure you have written your last name in full.",
				],
				"minLength" => 2,
			],
			"value" => urldecode($last_name),
			"sm" => 7,
		]], [
			"type" => "email",
			"autocomplete" => "email",
			"name" => "email",
			"icon" => "envelope",
			"label" => false,
			"placeholder" => "Email address",
			"validation" => [
				"required" => [
					"rule" => true,
					"msg" => "Please ensure you've entered a valid email address.",
				],
			],
			"value" => $email,
		], [
			"type" => "tel",
			"autocomplete" => "tel",
			"name" => "phone",
			"placeholder" => false,
			"validation" => [
				"tel" => [
					"rule" => true,
					"msg" => "Please ensure you enter your full mobile number.",
				],
			],
			"label" => false,
			"value" => $phone,
		], [
			"type" => "recaptcha",
			"action" => "insert_user",
		], [
			"type" => "checkbox",
			"name" => "tnc",
			"checked" => $tnc,
			"label" => href::a([
				"pre" => "I accept the ",
				"html" => "Terms and Conditions",
				"post" => ".",
				"hash" => [
					"rel_table" => "narrative",
					"rel_id" => "termsconditions",
				],
			]),
			"validation" => [
				"required" => [
					"rule" => true,
					"msg" => "Please make sure you read and accept our terms and conditions.",
				],
			],
			"value" => $tnc,
		]];
	}

	/**
	 * Internal only.
	 *
	 * @param null $a
	 *
	 * @return array
	 */
	public static function new($a = NULL)
	{
		if(is_array($a))
			extract($a);

		return [[[
			"name" => "first_name",
			"autocomplete" => "name given-name",
			"label" => false,
			"placeholder" => "First name",
			"required" => true,
			"value" => $first_name,
			"sm" => 5,
		], [
			"name" => "last_name",
			"autocomplete" => "name family-name",
			"label" => false,
			"placeholder" => "Last name",
			"required" => true,
			"value" => $last_name,
			"sm" => 7,
		]], [
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
			"value" => $email,
		], [
			"type" => "tel",
			"autocomplete" => "tel",
			"name" => "phone",
			"placeholder" => false,
			"validation" => [
				"tel" => [
					"rule" => true,
					"msg" => "Please ensure you enter a full mobile number.",
				],
			],
			"label" => false,
			"value" => $phone,
		], [
			"type" => "checkbox",
			"name" => "welcome_email",
			"checked" => false,
			"label" => [
				"title" => "Send welcome email",
				"desc" => "Sends a welcome email to the new user with a link to verify their account and set up a password.",
			],
		]];
	}

	/**
	 * @param null $a
	 *
	 * @return array[]
	 */
	public static function edit($a = NULL, ?bool $elevated = NULL)
	{
		if(is_array($a))
			extract($a);

		if($elevated){
			$desc = "Email address changes to verified accounts will result in emails going to the user's old and new addresses for verification.";
		}
		else {
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
					"msg" => "Please ensure you have written your first name in full.",
				],
				"minLength" => 2,
			],
			"value" => $first_name,
		], [
			"name" => "last_name",
			"autocomplete" => "name family-name",
			"label" => false,
			"placeholder" => "Last name",
			"validation" => [
				"required" => [
					"rule" => true,
					"msg" => "Please ensure you have written your last name in full.",
				],
				"minLength" => 2,
			],
			"value" => $last_name,
		], [
			"type" => "email",
			"icon" => "envelope",
			"label" => false,
			"placeholder" => "Email address",
			"disabled" => $disabled,
			"desc" => $desc,
			"value" => $email,
			"name" => "email",
			"required" => true,
		], [
			"type" => "tel",
			"autocomplete" => "tel",
			"name" => "phone",
			"placeholder" => false,
			//			"icon" => "phone",
			"validation" => [
				"tel" => [
					"rule" => true,
					"msg" => "Please ensure you enter your full mobile number.",
				],
			],
			"label" => false,
			"value" => $phone,
		]];
	}

	/**
	 * @param null $a
	 *
	 * @return array|array[]
	 */
	public static function editEmail($a = NULL): array
	{
		if(is_array($a))
			extract($a);

		return [[
			"html" => "<p>If you wish to update your email address, 
			enter the new one here along with your current password. 
			An email will be sent to your new address with a verification 
			link to complete the update process.</p>
			<p>Until you complete the process, please continue to use
			your current address to log in.</p>",
		], [
			"type" => "email",
			"autocomplete" => "off",
			"name" => "new_email",
			"icon" => "envelope",
			"label" => false,
			"placeholder" => "New email address",
			"validation" => [
				"required" => [
					"rule" => true,
					"msg" => "Please ensure you've entered a valid email address.",
				],
			],
		], [
			"icon" => "key",
			"type" => "password",
			"name" => "password",
			"placeholder" => "Current password",
			"label" => false,
			"required" => "For added security, please enter your current password.",
			"autocomplete" => "new-password",
		]];
	}

	public static function editSignature($a = NULL): array
	{
		if(is_array($a))
			extract($a);

		if($signature_id){
			$storage = RemoteStorage::create();
			if($storage->fileExists($user_id, $signature_id)){
				$signature = $storage->getData($user_id, $signature_id);
			}
		}

		$fields[] = [
			"type" => "html",
			"html" => "<p>
			By adding your signature it can
			be used in workflows in lieu
			of you siging yourself. 
			Please consult with your
			subscription owner if you
			are unsure if this is right for you.
			</p>",
		];

		$fields[] = [
			"type" => "signature",
			"name" => "signature",
			"value" => $signature,
			"label" => false,
			"required" => "Ensure you've added your signature.",
		];

		# If the user has a password, ask them to repeat it
		if($password){
			// SSO users don't have passwords
			$fields[] = [
				"icon" => "key",
				"type" => "password",
				"name" => "password",
				"placeholder" => "Current password",
				"label" => false,
				"required" => "For added security, please enter your current password.",
				"autocomplete" => "new-password",
				"parent_style" => [
					"margin-top" => "1rem"
				]
			];
		}


		return $fields;
	}

	public static function editLanguage(?array $a = NULL): array
	{
		if(is_array($a))
			extract($a);

		$fields[] = [
			"type" => "select",
			"name" => "language_id",
			"value" => $language_id,
			"options" => Language::getOptionsFromLanguages(Language::getAllLanguages(), false, true, true, true)
		];

		return $fields;
	}

	/**
	 * Used to set a new password,
	 * either for new user or user who
	 * has forgotten their password.
	 *
	 * @param null $a
	 *
	 * @return array
	 */
	public static function newPassword($a = NULL)
	{
		if(is_array($a))
			extract($a);

		$fields = self::hiddenFields($a, ["key"]);
		$fields[] = self::fieldNewPassword();
		$fields[] = self::fieldRepeatNewPassword();

		return $fields;
	}

	/**
	 * Used to update an existing password.
	 * Assumes the user knows their existing password
	 *
	 * @param array|null $a
	 *
	 * @return array
	 */
	public static function editPassword(?array $a = NULL): array
	{
		if(is_array($a))
			extract($a);

		$fields[] = self::fieldCurrentPassword();
		$fields[] = self::fieldNewPassword();
		$fields[] = self::fieldRepeatNewPassword();

		return $fields;
	}

	/**
	 * Used to create link to reset password.
	 *
	 * @param null $a
	 *
	 * @return array
	 */
	public static function resetPassword($a = NULL)
	{
		if(is_array($a))
			extract($a);

		return [[
			"html" => "<p>Enter the email address you used to register with. If the address is valid, an email will be sent to you with a link to reset your password.</p>",
		], [
			"type" => "email",
			"name" => "email",
			"label" => false,
			"required" => true,
			"value" => $email,
		], [
			"type" => "recaptcha",
			"action" => "reset_password",
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
		if(is_array($a))
			extract($a);

		return [[
			"name" => "code",
			"label" => false,
			"placeholder" => "Enter code here",
			"required" => true,
			"validation" => [
				"minLength" => [
					"rule" => 4,
					"msg" => "Your code doesn't seem to be complete.",
				],
				"maxLength" => [
					"rule" => 4,
					"msg" => "Ensure you enter the code only.",
				],
			],
			"desc" => Countdown::generate([
				"modify" => "+15 minutes",
				"stop" => "Your code has expired, please cancel and log in again.",
				"pre" => "The code is not case sensitive and will expire in ",
				"post" => ".",
				"callback" => false,
			]),
			"autocomplete" => "off",
		], [
			"type" => "hidden",
			"name" => "remember",
			"value" => $remember,
		]];
	}
}