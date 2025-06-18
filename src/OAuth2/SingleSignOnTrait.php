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
		foreach(OAuth2Handler::USER_SSO_DATA_FIELDS as $key => $field){
			# If the provider already has a value for this field, skip it
			if($user[$key]){
				continue;
			}

			# Go thru each provider and their alternative provider field names
			foreach($field['provider'] as $provider => $provider_fields){
				# If the provider field names is not an array, make it one
				if(!is_array($provider_fields)){
					$provider_fields = [$provider_fields];
				}

				# Go thru each provider field name and if the user has a value for it, use that value instead
				foreach($provider_fields as $provider_field){
					# If the user has a value for this field, use it
					if(key_exists($provider_field, $user)){
						# If the value is an array, use the first value
						if(is_array($user[$provider_field])){
							$user[$key] = reset($user[$provider_field]);
							continue 3;
						}
						# Use the value and continue to the next field
						$user[$key] = $user[$provider_field];
						continue 3;
					}
				}
			}
		}

		# If the user has a first name and/or a last name, create a full name
		$user['full_name'] = implode(" ", array_filter([$user['first_name'], $user['last_name']]));
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
					"sso" => true,
					"provider" => $provider,
					"callback" => $callback,
				],
			],
		]);
	}
}