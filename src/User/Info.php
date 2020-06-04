<?php


namespace App\Common\User;

use App\Common\SQL\Info\Common;
use App\Common\SQL\Info\InfoInterface;
use App\Common\str;

/**
 * Class Info
 * @package App\Common\User
 */
class Info extends Common implements InfoInterface {
	/**
	 * @param array $a
	 */
	public function prepare(&$a) : void
	{
		$a['left_join'][] = "user_role";
	}
	public static function format(array &$row) : void
	{
		# Add "name" and "full_name", and format first and last names
		str::addNames($row);

		# Clean up email
		$row['email'] = strtolower($row['email']);
		//email addresses must be parsed as lowercase
		//because they're used in string comparisons

		# If no role hs been allocated, assume user
		$row['last_role'] =	$row['last_role'] ?: "user";
	}
}