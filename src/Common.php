<?php


namespace App\Common;


use App\Common\User\User;

class Common {
	/**
	 * @var Log
	 */
	protected $log;

	/**
	 * @var Output
	 */
	protected $output;

	/**
	 * @var str
	 */
	protected $str;


	function __construct () {
		$this->hash = Hash::getInstance();
		$this->log = Log::getInstance();
		$this->pa = PA::getInstance();
		$this->output = Output::getInstance();
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
	 * Shortcut for the user->accessDenied method.
	 *
	 * @return bool
	 */
	function accessDenied(){
		return $this->user->accessDenied();
	}

	/**
	 * Generic insert function that inserts a new row with data
	 * into a table and returns the new id.
	 *
	 * Extended/replaced/superseded by rel_table->method() custom methods.
	 *
	 * @param $a
	 *
	 * @return int Returns the newly created ID or false on error.
	 */
	function insert($a, $silent = NULL) {
		extract($a);

		if (!$this->user->isAllowedTo($action, $rel_table)) {
			return $this->accessDenied();
		}

		# Specifically for tables with an order column
		if(in_array("order",$this->sql->getTableMetadata($rel_table, true))
			&& !$vars['order']){
			//If the table has an "order" column, but no order value has been sent as part of the insert
			$o = $this->sql->select([
				"columns" => [
					"max_order" => "max(`{$rel_table}`.`order`)"
				],
				"table" => $rel_table,
				"limit" => 1
			]);
			//Get the current max order number
			$vars['order'] = $o['max_order'] + 1;
			//Set the new max order number
		}

		# Insert the data. The assumption here is that the data has already been vetted in the custom methods
		if(!$insert_id = $this->sql->insert([
			"table" => $rel_table,
			"set" => $vars,
		])){
			return false;
		}

		# Set callback
		if($vars['callback']){
			//if a callback has been requested
			$this->hash->set($vars['callback']);
		} else if(Request::methodAvailable(Request::findClass($rel_table), "view", "public")) {
			//if a rel_table/rel_id/view class exists
			$this->hash->set([
				"rel_table" => $rel_table,
				"rel_id" => $insert_id
			]);
		} else if($silent){
			//if we're asked to be silent (no change to hash)
			$this->hash->silent();
		} else {
			//the default is otherwise to go back
			$this->hash->set(-1);
		}

		# Notify user
		if(!$silent){
			$this->log->success([
				"icon" => "indent",
				"title" => str::title("Great success!"),
				"message" => str::title("Created a new {$rel_table} record.")
			]);
		}

		return $insert_id;
	}
}