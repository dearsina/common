<?php

namespace App\Common\OAuth2;

use App\Common\href;
use App\Common\Img;

trait SingleSignOnTrait {
	public static function unifyMember(array &$member): void
	{
		$member['email'] = $member['email'] ?: $member['mail'];
	}

	/**
	 * Creates a simplified user array for the most important keys
	 * that will be the same across all SSO providers.
	 *
	 * @param array $user
	 *
	 * @return void
	 */
	public static function unifyUser(array &$user): void
	{
		$user['email'] = $user['email'] ?: $user['mail'];
		$user['phone'] = $user['number'] ?: $user['mobilePhone'];

		$user['first_name'] = $user['first_name'] ?: $user['givenName'];
		$user['last_name'] = $user['last_name'] ?: $user['surname'];

		if($user['first_name'] || $user['last_name']){
			$user['name'] = trim($user['first_name'] . " " . $user['last_name']);
		}

		else if($user['displayName']){
			$user['name'] = $user['displayName'];
		}
		else if($user['userPrincipalName']){
			$user['name'] = $user['userPrincipalName'];
		}
		else {
			$user['name'] = $user['email'];
		}

		if($user['officeLocation'] || $user['streetAddress']){
			$user['office_location'] = $user['officeLocation'] ?: $user['streetAddress'];
		}

		if($user['jobTitle']){
			$user['job_title'] = $user['jobTitle'];
		}

		if($user['preferredLanguage']){
			$user['language'] = $user['preferredLanguage'];
		}

		if($user['businessPhones']){
			if(is_array($user['businessPhones'])){
				$business_phones = array_filter($user['businessPhones']);
				$user['business_phone'] = reset($business_phones);
			}
			else {
				$user['business_phone'] = $user['businessPhones'];
			}
		}
	}

	public static function getSignOnButton(string $provider, ?string $callback = NULL, ?string $user_id = NULL): string
	{
		switch($provider) {
		case "entra":
			$src = "https://learn.microsoft.com/en-us/entra/identity-platform/media/howto-add-branding-in-apps/ms-symbollockup_signin_light.svg";
			break;
		}

		return href::a([
			"html" => Img::generate([
				"src" => $src,
			]),
			"hash" => [
				"rel_table" => "user",
				"rel_id" => $user_id,
				"action" => "oauth2",
				"vars" => [
					"provider" => $provider,
					"callback" => $callback,
				],
			],
		]);
	}
}