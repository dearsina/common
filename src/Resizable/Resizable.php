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

		if($vars['rel_table']){
			return $this->logRelTableChange($a);
		}

		# If the user has already resized this modal, update the record
		if($resizable = $this->sql->select([
			"db" => "ui",
			"table" => "resizable",
			"where" => [
				"user_id" => $this->user->getId(),
				"modal_id" => $vars['modal_id'],
			],
			"limit" => 1,
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

	private function logRelTableChange(array $a): bool
	{
		extract($a);

		if(!$vars['columns']){
			$this->sql->delete([
				"db" => "ui",
				"table" => "resizable",
				"where" => [
					"user_id" => $this->user->getId(),
					"rel_id" => $vars['rel_id'],
				],
			]);
			return true;
		}

		# If the user already has a record for this dashboard tile, update it
		if($resizable = $this->sql->select([
			"db" => "ui",
			"table" => "resizable",
			"where" => [
				"user_id" => $this->user->getId(),
				"rel_id" => $vars['rel_id'],
			],
			"limit" => 1,
		])){
			$this->sql->update([
				"db" => "ui",
				"table" => "resizable",
				"set" => [
					"columns" => $vars['columns']
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
				"rel_table" => $vars['rel_table'],
				"rel_id" => $vars['rel_id'],
				"columns" => $vars['columns']
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
				"modal_id" => $modal_id,
			],
			"limit" => 1,
		])){
			return;
		}

		$data['dimensions'] = [
			"width" => $resizable['width'],
			"height" => $resizable['height'],
		];
	}

	public static function getColumnData(?string $rel_id = NULL): ?array
	{
		if(!$rel_id){
			return NULL;
		}

		$user = new User();
		if(!$user->isLoggedIn()){
			return NULL;
		}

		if(!$resizable = mySQL::getInstance()->select([
			"db" => "ui",
			"table" => "resizable",
			"where" => [
				"user_id" => $user->getId(),
				"rel_id" => $rel_id,
			],
			"limit" => 1,
		])){
			return NULL;
		}

		if(!$resizable['columns']){
			return NULL;
		}

		foreach($resizable['columns'] as $column){
			# The column number stored is 1-based, so we need to subtract 1 to make it 0-based
			if(is_numeric($column['column'])){
				// But only if it's a column number and not "button"
				$column['column'] -= 1;
			}
			$columns[$column['column']] = $column;
		}

		return $columns;
	}


}