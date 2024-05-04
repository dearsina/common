<?php

namespace App\Common\User;

use App\Common\str;
use App\UI\Icon;

class Buttons {
	public static function edit(array $user): array
	{
		return  [
			"hash" => [
				"rel_table" => "user",
				"rel_id" => $user['user_id'],
				"action" => "edit",
				"vars" => [
					"callback" => str::generate_uri([
						"rel_table" => "user",
						"rel_id" => $user['user_id'],
					], true),
				],
			],
			"title" => "Edit...",
			"icon" => Icon::get("edit"),
		];
	}

	public static function editEmail(array $user): array
	{
		return  [
			"hash" => [
				"rel_table" => "user",
				"rel_id" => $user['user_id'],
				"action" => "edit_email",
			],
			"title" => "Edit email address...",
			"icon" => "envelope",
		];
	}

	public static function updatePassword(array $user): array
	{
		return  [
			"hash" => [
				"rel_table" => "user",
				"rel_id" => $user['user_id'],
				"action" => "edit_password",
			],
			"title" => "Update password...",
			"icon" => "key",
		];
	}

	public static function sendVerificationEmail(array $user): array
	{
		return  [
			"hash" => [
				"rel_table" => "user",
				"rel_id" => $user['user_id'],
				"action" => "send_verify_email",
			],
			"title" => "Resend verify email",
			"alt" => "Resend the user a verify email to {$user['email']} with a verification code",
			"icon" => Icon::get("send"),
		];
	}

	public static function toggleTwoFactorAuthentication(array $user): array
	{
		if($user['2fa_enabled']){
			$title = "Disable two-factor authentication...";
			$approve = [
				"icon" => Icon::get("2fa"),
				"colour" => "warning",
				"title" => "Disable two-factor authentication?",
				"message" => "Your account is more secure when you need a password and a verification code to sign in. If you remove this extra layer of security, you will only be asked for a password when you sign in. It might be easier for someone to break into your account.",
			];
		} else {
			$title = "Enable two-factor authentication";
			$approve = false;
		}

		return  [
			"hash" => [
				"rel_table" => "user",
				"rel_id" => $user['user_id'],
				"action" => "toggle_2FA",
			],
			"title" => $title,
			"approve" => $approve,
			"icon" => Icon::get("2fa"),
		];
	}

	public static function togglePasswordExpiry(array $user): array
	{
		if($user['password_expiry']){
			$title = "Disable password expiry...";
			$approve = [
				"icon" => "recycle",
				"colour" => "warning",
				"title" => "Disable password expiry?",
				"message" => "Your account is more secure when you refresh your passwords every so often. Disabling password expiry allows you to keep your current password forever.",
			];
		} else {
			$title = "Enable password expiry";
			$approve = false;
		}

		return  [
			"hash" => [
				"rel_table" => "user",
				"rel_id" => $user['user_id'],
				"action" => "toggle_password_expiry",
			],
			"title" => $title,
			"approve" => $approve,
			"icon" => "recycle",
		];
	}

	public static function close(array $user): array
	{
		return [
			"hash" => [
				"rel_table" => "user",
				"rel_id" => $user['user_id'],
				"action" => "close",
				"variables" => [
					"callback" => "logout",
				],
			],
			"title" => "Close account...",
			"icon" => "times",
			"approve" => [
				"colour" => "red",
				"title" => "Close your account",
				"message" => "Are you sure you want to close your account? All your data will be removed immediately. This cannot be undone.",
			],
		];
	}

	public static function disconnectSso(array $user): array
	{
		return [
			"hash" => [
				"rel_table" => "user",
				"rel_id" => $user['user_id'],
				"action" => "disconnect_sso",
				"variables" => [
					"callback" => "logout",
				],
			],
			"title" => "Disconnect single sign-on...",
			"icon" => "times",
			"approve" => [
				"colour" => "red",
				"title" => "Disconnect single sign-on",
				"message" => "Are you sure you want to disconnect single sign-on?",
			],
		];
	}
}