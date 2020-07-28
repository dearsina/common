<?php

namespace App\Common\Connection;

use App\Common\Common;

class Connection extends Common {
	public static function get(?string $user_id = NULL): ?array
	{
		$connection = new Connection();
		return $connection->getConnection($user_id);
	}

	public function getConnection(string $user_id = NULL): ?array
	{
		if(!$user_id){
			// If no user ID has been given, use the global user ID
			global $user_id;

			# In case the user has not logged in, OR if the user has several sessions open
			$where["session_id"] = session_id();
		}

		if($user_id){
			$where["user_id"] = $user_id;
		}

		# We're only interested in open connections
		$where['closed'] = NULL;

		return $this->sql->select([
			"table" => "connection",
			"where" => $where,
			"order_by" => [
				"created" => "DESC"
			],
			"limit" => 1
		]);
	}
}