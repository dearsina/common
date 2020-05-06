<?php


namespace App\Common\SQL\Info;

use App\Common\str;

/**
 * Class Common
 * 
 * For shared methods accross the different `rel_table\info` classes.
 * 
 * @package App\Common\SQL\Info
 */
class Common extends \App\Common\Common {
	/**
	 * Add "name" and "full_name", and format first and last names.
	 *
	 * @param $row
	 *
	 * @return bool
	 */
	public function addNames(&$row) : bool
	{
		if(!$row){
			return false;
		}

		$users = str::isNumericArray($row) ? $row : [$row];
		//If there is only one user, add a numerical key index

		# Go thru each user, even if there is only one
		foreach($users as $id => $user){
			$user['first_name'] = str::title($user['first_name'], true);
			$user['last_name'] = str::title($user['last_name'], true);
			$name = "{$user['first_name']} {$user['last_name']}";
			$user['name'] = $name;
			$user['full_name'] = $name;
			$users[$id] = $user;
		}

		$row = str::isAssociativeArray($row) ? reset($users) : $users;
		//If there was only one user, skip the numerical key index

		return true;
	}
}