<?php


namespace App\Common;

use App\Common\SQL\Factory;
use App\Common\SQL\Info\Info;

use App\Common\User\User;


abstract class Common {
	/**
	 * @var Log
	 */
	public $log;

	/**
	 * @var Output
	 */
	public $output;

	/**
	 * @var str
	 */
	public $str;

	/**
	 * @var Hash
	 */
	public $hash;

	/**
	 * @var SQL\mySQL\mySQL
	 */
	public $sql;

	/**
	 * @var User
	 */
	public $user;

	function __construct () {
		$this->hash = Hash::getInstance();
		$this->log = Log::getInstance();
		$this->pa = PA::getInstance();
		$this->output = Output::getInstance();
		$this->sql = Factory::getInstance();

		/**
		 * To avoid short-circuiting the script,
		 * classes that are extended by Common(),
		 * but that also are initiated in Common(),
		 * are excused.
		 */
		if(in_array(end(explode("\\", get_called_class())), ["User"])){
			return true;
		}

		$this->user = new User();
	}

	/**
	 * Set attributes, use custom setter methods,
	 * if they exist. Assumes setter methods
	 * are in the following format: `$this->setKey($val);`
	 *
	 * @param array|bool $a An array of attributes to set in the $key => $val format.
	 *
	 * @return bool
	 */
	function setAttr($a = NULL){
		if(!is_array($a)){
			return true;
		}
		foreach($a as $key => $val){
			$method = "set".ucwords($key);
			if (method_exists($this, $method)) {
				//if a custom setter method exists, use it
				$this->$method($val);
			} else {
				$this->$key = $val;
			}
		}
		return true;
	}

	/**
	 * Shortcut for complex, repeated SQL queries. Simple syntax:
	 * <code>
	 * $rel = $this->info($rel_table, $rel_id);
	 * </code>
	 * alternatively, add more colour:
	 * <code>
	 * $rel = $this->info([
	 *	"rel_table" => $rel_table,
	 * 	"rel_id" => $rel_id,
	 * 	"where" => [
	 * 		"key" => "val"
	 * 	]
	 * ]);
	 * </code>
	 *
	 * @param array|string	$rel_table_or_array
	 * @param string|null 	$rel_id
	 *
	 * @return bool|array
	 * @throws \Exception
	 */
	protected function info($rel_table_or_array, ?string $rel_id = NULL)
	{
		$info = Info::getInstance();
		return $info->getInfo($rel_table_or_array, $rel_id);
	}

	/**
	 * Get and set permissions for users.
	 *
	 * @return Permission
	 */
	protected function permission(){
		$permission = new Permission();
		return $permission;
	}

	/**
	 * Shortcut for the user->accessDenied method.
	 *
	 * @param array|null $a The current URL (for the callback)
	 *
	 * @return bool
	 */
	function accessDenied(?array $a = NULL){
		return $this->user->accessDenied($a);
	}

	/**
	 * Given a table name, will return the
	 * next order number.
	 *
	 * @param $rel_table
	 *
	 * @return bool|int
	 */
	public function getOrder(string $rel_table){
		# Ensure the table has an order column
		if (!in_array("order", array_keys($this->sql->getTableMetadata($rel_table, true)))){
			return false;
		}

		# Get the highest order
		$$rel_table = $this->sql->select([
			"columns" => [
				"max_order" => "max(`{$rel_table}`.`order`)",
			],
			"table" => $rel_table,
			"limit" => 1,
		]);

		# Return the number one higher
		return ${$rel_table}['max_order'] + 1;
	}

	public function setOrder(array $a, $silent = NULL){
		extract($a);

		# No change to order
		if($vars['old_index'] == $vars['new_index']){
			//Nothing to do
			return true;
		}

		# Up
		if($vars['old_index'] > $vars['new_index']){
			$end = (int) $vars['old_index'];
			$beginning = (int) $vars['new_index'];
			$end--;
			$direction = "+";
		}

		# Down
		if($vars['old_index'] < $vars['new_index']) {
			$beginning = (int) $vars['old_index'];
			$end = (int) $vars['new_index'];
			$beginning++;
			$direction = "-";
		}

		# Reorder all the other affected lines
		$this->sql->update([
			"table" => $rel_table,
			"set" => [
				"order" => "`{$rel_table}`.`order` {$direction} 1"
			],
			"where" => [
				"`order` between {$beginning} and {$end}"
			]
		]);

		# Reorder the dragged row
		$this->sql->update([
			"table" => $rel_table,
			"id" => $rel_id,
			"set" => [
				"order" => $vars['new_index']
			]
		]);

		if(!$silent){
			$this->log->success([
				"icon" => "random",
				"message" => "Order updated"
			]);
		}

		return true;
	}

//	/**
//	 * Generic insert function that inserts a new row with data
//	 * into a table and returns the new id.
//	 *
//	 * Extended/replaced/superseded by rel_table->method() custom methods.
//	 *
//	 * @param array $a
//	 *
//	 * @return int Returns the newly created ID or false on error.
//	 */
//	function insert(array $a, $silent = NULL) {
//		extract($a);
//
//		if (!$this->user->isAllowedTo($action, $rel_table)) {
//			return $this->accessDenied();
//		}
//
//		# Specifically for tables with an order column
//		if(in_array("order",$this->sql->getTableMetadata($rel_table, true))
//			&& !$vars['order']){
//			//If the table has an "order" column, but no order value has been sent as part of the insert
//			$o = $this->sql->select([
//				"columns" => [
//					"max_order" => "max(`{$rel_table}`.`order`)"
//				],
//				"table" => $rel_table,
//				"limit" => 1
//			]);
//			//Get the current max order number
//			$vars['order'] = $o['max_order'] + 1;
//			//Set the new max order number
//		}
//
//		# Insert the data. The assumption here is that the data has already been vetted in the custom methods
//		if(!$insert_id = $this->sql->insert([
//			"table" => $rel_table,
//			"set" => $vars,
//		])){
//			return false;
//		}
//
//		# Set callback
//		if($vars['callback']){
//			//if a callback has been requested
//			$this->hash->set($vars['callback']);
//		} else if(str::methodAvailable(str::findClass($rel_table), "view", "public")) {
//			//if a rel_table/rel_id/view class exists
//			$this->hash->set([
//				"rel_table" => $rel_table,
//				"rel_id" => $insert_id
//			]);
//		} else if($silent){
//			//if we're asked to be silent (no change to hash)
//			$this->hash->silent();
//		} else {
//			//the default is otherwise to go back
//			$this->hash->set(-1);
//		}
//
//		# Notify user
//		if(!$silent){
//			$this->log->success([
//				"icon" => "indent",
//				"title" => str::title("Great success!"),
//				"message" => str::title("Created a new {$rel_table} record.")
//			]);
//		}
//
//		return $insert_id;
//	}
}