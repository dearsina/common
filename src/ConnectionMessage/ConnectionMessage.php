<?php

namespace App\Common\ConnectionMessage;

use App\Common\SQL\mySQL\mySQL;

/**
 * A class to handle messages to connections that
 * do not have WebSockets enabled.
 */
class ConnectionMessage extends \App\Common\Prototype {
	/**
	 * Used by the PA::speak method to store messages
	 * that were meant for recipients that don't have
	 * WebSockets.
	 *
	 * @param string $connection_id
	 * @param array  $data
	 */
	public static function store(string $connection_id, array $data): void
	{
		mySQL::getInstance()->insert([
			"table" => "connection_message",
			"set" => [
				"connection_id" => $connection_id,
				"message" => json_encode($data),
			],
			"html" => "message"
		]);
	}

	/**
	 * Switch a websocket connection to a pull-request connection.
	 * Will need the connection ID, matching session ID and IP address.
	 *
	 * @param array $a
	 *
	 * @return bool
	 */
	public function switch(array $a): bool
	{
		extract($a);

		# Ensure the connection isn't being spoofed
		if(!$this->sql->select([
			"table" => "connection",
			"id" => $rel_id,
			"where" => [
				"session_id" => session_id(),
				"ip" => $_SERVER['REMOTE_ADDR']
			]
		])){
			return false;
		}

		# Remove the FD (WebSocket recipient) number, now that the websocket has died
		$this->sql->update([
			"table" => "connection",
			"id" => $rel_id,
			"set" => [
				"fd" => NULL,
			]
		]);

		return true;
	}

	/**
	 * This method will be pinged every
	 * 5 seconds from connections where
	 * WebSockets was not established.
	 *
	 * @param array $a
	 *
	 * @return bool
	 */
	public function view(array $a): bool
	{
		extract($a);

		if(!$messages = $this->sql->select([
			"table" => $rel_table,
			"join" => [[
				"table" => "connection",
				"on" => "connection_id",
				"where" => [
					"session_id" => session_id(),
					"ip" => $_SERVER['REMOTE_ADDR']
				]
			]],
			"where" => [
				"connection_id" => $rel_id,
				"read" => NULL
			],
			"order_by" => [
				"created" => "ASC"
			]
		])){
			return true;
		}

		# Get the oldest message
		$message = array_shift($messages);

		$data = json_decode($message['message'], true);
		$this->output->set($data);

		global $user_id;

		# Mark the one message as read
		$this->sql->update([
			"table" => $rel_table,
			"id" => $message['connection_message_id'],
			"set" => [
				"read" => "NOW()"
			],
			"user_id" => $user_id ?: false
		]);

		# Return the number of messages remaining
		$this->output->setVar("messages_remaining", count($messages));

		return true;
	}
}