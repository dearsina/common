<?php

namespace App\Common\Resizable;

use App\Common\Prototype;
use App\Common\SQL\mySQL\mySQL;
use App\Common\User\User;

class Resizable extends Prototype {

	/**
	 * Logs resizing of modals so that the user doesn't have to resize them
	 * every time they open them. Only applies to logged-in users accessing
	 * modals with unique IDs.
	 *
	 * @param array $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function log(array $a): bool
	{
		extract($a);

		# Logging of resizable events is not required for non-logged in users
		if(!$this->user->isLoggedIn()){
			return true;
		}

		# If the user has already resized this modal, update the record
		if($resizable = $this->sql->select([
			"db" => "ui",
			"table" => "resizable",
			"where" => [
				"user_id" => $this->user->getId(),
				"modal_id" => $vars['modal_id']
			],
			"limit" => 1
		])){
			$this->sql->update([
				"db" => "ui",
				"table" => "resizable",
				"set" => [
					"width" => $vars['width'],
					"height" => $vars['height'],
				],
				"id" => $resizable['resizable_id'],
			]);
			return true;
		}

		# Otherwise, insert a new record
		$this->sql->insert([
			"db" => "ui",
			"table" => "resizable",
			"set" => [
				"user_id" => $this->user->getId(),
				"modal_id" => $vars['modal_id'],
				"width" => $vars['width'],
				"height" => $vars['height'],
			],
		]);

	    return true;
	}

	public static function setDimensions(?array &$data = [], ?string $modal_id = NULL): void
	{
		if(!$modal_id){
			return;
		}

		$user = new User();
		if(!$user->isLoggedIn()){
			return;
		}

		if(!$resizable = mySQL::getInstance()->select([
			"db" => "ui",
			"table" => "resizable",
			"where" => [
				"user_id" => $user->getId(),
				"modal_id" => $modal_id
			],
			"limit" => 1
		])){
			return;
		}

		$data['dimensions'] = [
			"width" => $resizable['width'],
			"height" => $resizable['height'],
		];
	}


}