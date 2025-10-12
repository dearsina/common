<?php


namespace App\Common\User;

use App\Common\SQL\Info\Prototype;
use App\Common\SQL\Info\InfoInterface;
use App\Common\str;

/**
 * Class Info
 * @package App\Common\User
 */
class Info extends Prototype implements InfoInterface {
	/**
	 * @param array $a
	 */
	public static function prepare(array &$a, ?array $joins): void
	{
		$a['include_meta'] = true;
		$a['left_join'][] = "user_role";
		$a['left_join'][] = [
			"columns" => [
				"role_id",
				"role",
				"icon",
			],
			"table" => "role",
			"on" => [
				"role" => ["user_role", "rel_table"],
			],
		];
	}

	public static function format(array &$row): void
	{
		# Add "name" and "full_name", and format first and last names
		str::addNames($row);

		# Clean up email
		$row['email'] = strtolower($row['email']);
		//email addresses must be parsed as lowercase
		//because they're used in string comparisons

		# Add email domain as a separate field
		$row['email_domain'] = strtolower(substr(strrchr($row['email'], "@"), 1));

		# If no role hs been allocated, assume user
		$row['last_role'] = $row['last_role'] ?: "user";

		# Grabs the role ID, title and icon and places it along the user role data
		if($row['user_role']){
			//if this method is used outside of the info() class
			foreach($row['user_role'] as $id => $role){
				$row['user_role'][$id] = array_merge($row['user_role'][$id], $role['role'][0] ?:[]);
			}
		}

		# Use the SSO language if no language has been set for the user
		if(!$row['language_id']){
			$row['language_id'] = $row['sso_data']['language'];
		}
	}
}