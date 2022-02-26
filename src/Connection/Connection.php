<?php

namespace App\Common\Connection;

use App\Common\Prototype;
use App\Common\Geolocation\Geolocation;
use Exception;

class Connection extends Prototype {
	/**
	 * Given a user ID, get the most recent connection ID.
	 *
	 * @param string|null $user_id
	 *
	 * @return array|null
	 */
	public static function get(?string $user_id = NULL): ?array
	{
		$connection = new Connection();
		return $connection->getConnection($user_id);
	}

	/**
	 * Records a connection's details, returns the connection ID.
	 * The owner of the connection is mostly a user, but could also
	 * be an API call. In which case the user is the subscription owner.
	 *
	 * Sets the opened column to NOW().
	 *
	 * @return string The current connection ID.
	 * @throws Exception
	 */
	public static function open(): string
	{
		$connection = new Connection();
		return $connection->setConnection([
			"opened" => "NOW()"
		]);
	}
	public static function close(string $connection_id, ?array $set = []): void
	{
		$connection = new Connection();
		$connection->closeConnection($connection_id, $set);
	}

	private function closeConnection(string $connection_id, ?array $set = []): void
	{
		$this->sql->update([
			"table" => "connection",
			"id" => $connection_id,
			"set" => array_merge([
				"closed" => "NOW()"
			], $set),
			"user_id" => false
		]);
	}

	/**
	 * Records a connection's details, returns the connection ID.
	 * The owner of the connection is mostly a user, but could also
	 * be an API call. In which case the user is the subscription owner.
	 *
	 * @param array|null $set
	 *
	 * @return string The current connection ID.
	 * @throws Exception
	 */
	public function setConnection(?array $set = []): string
	{
		# Record the user's geolocation
		Geolocation::get();

		# Get the user's user agent ID
		$user_agent_id = $this->getUserAgentId();

		# If the user is logged in, get their user ID
		global $user_id;

		# Get the set (that makes a unique connection)
		$set = array_merge([
			"session_id" => session_id(),
			"ip" => $_SERVER['REMOTE_ADDR'],
			"user_agent_id" => $user_agent_id,
			"user_id" => $user_id
		],$set ?: []);

		# If the connection has already been made (and is not closed), return it
		if($connection = $this->sql->select([
			"table" => "connection",
			"where" => array_merge($set, [
				"closed" => NULL
			]),
			"limit" => 1
		])){
			return $connection['connection_id'];
		}

		# Otherwise, create a new connection
		if(!$connection_id = $this->sql->insert([
			"table" => "connection",
			"set" => $set
		])){
			//The new connection was not saved
			throw new Exception("Unable to store connection details");
		}

		return $connection_id;
	}

	/**
	 * Returns an ID for the current user's user agent
	 * from the user_agent table.
	 *
	 * @return bool|int|mixed
	 * @throws Exception
	 */
	public function getUserAgentId(): string
	{
		# If the agent already exists, get the existing one
		if($user_agent = $this->sql->select([
			"table" => "user_agent",
			"where" => [
				"desc" => $_SERVER['HTTP_USER_AGENT'],
			],
			"limit" => 1
		])) {
			//if it already exists
			return $user_agent['user_agent_id'];
		}

		//If the user agent does not exist, create a new record
		if(!$user_agent_id = $this->sql->insert([
			"table" => 'user_agent',
			"set" => [
				"desc" => $_SERVER['HTTP_USER_AGENT']
			],
		])){
			throw new Exception("Unable to create a user agent record.");
		}

		return $user_agent_id;
	}

	private function getConnection(string $user_id = NULL): ?array
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