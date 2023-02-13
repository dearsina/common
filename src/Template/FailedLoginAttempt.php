<?php


namespace App\Common\Template;


use App\Common\str;
use App\Common\User\User;

class FailedLoginAttempt extends Template implements TemplateInterface {

	/**
	 * @inheritDoc
	 */
	public function getSubject(): string
	{
		return "Failed login attempt";
	}

	/**
	 * @inheritDoc
	 */
	public function getPreheader(): ?string
	{
		return "Someone tried to log in to your account with the wrong password.";
	}

	/**
	 * @inheritDoc
	 */
	public function getBody(): array
	{
		extract($this->getVariables());

		$location = implode(", ", array_filter([
			$geolocation['city'],
			$geolocation['region_name'],
			$geolocation['country_name']
		]));

		$body = <<<EOF
<p>
Someone tried to log into your account with the wrong password.<br>
Their IP address was: <code>{$geolocation['ip']}</code><br>
Their location was: <code>{$location}</code>
</p>
<p>If this was you, you can ignore this email.</p>
EOF;

		if($user['2fa_enabled'] == 1){
			$body .= <<<EOF
<p>You have enabled two-factor authentication on your account, so even if someone
knows your password, they won't be able to log in without your phone.</p>
EOF;
		}

		else {
			$body .= <<<EOF
<p>You have not enabled two-factor authentication on your account, which means that if someone
gets to know your password, they will be able to log in to your account. Please consider
enabling two-factor authentication.</p>
EOF;
		}

		if($remaining_login_attempts){
			$body .= "<p>After {$remaining_login_attempts} more failed login ".
				str::pluralise_if($remaining_login_attempts, "attempt").", your account will be locked.</p>";
		}

		else {
			$body .= <<<EOF
<p>Your account has been locked, so no more password attempts can be made.
Please reset your password or contact your subscription administrator to unlock it.</p>
EOF;
		}

		return [[
			"bg_colour" => "silent",
			"copy" => [
				"title" => [
					"align" => "left",
					"title" => "Failed login attempt",
					"colour" => "primary",
				],
				"body" => [
					"body" => $body,
					"align" => "left"
				]
			],
		]];
	}
}