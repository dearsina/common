<?php


namespace App\Common;

use App\Common\Exception\BadRequest;
use App\Common\SQL\Factory;
use App\Common\SQL\Info\Info;

use App\Common\User\User;
use App\UI\Icon;
use App\UI\Table;
use Exception;
use ReflectionClass;
use function GuzzleHttp\Psr7\str;


/**
 * Class Common
 * The parent class for both CommonModal and CommonCard.
 * @package App\Common
 */
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
	 * @var Hash
	 */
	public $hash;

	/**
	 * @var SQL\mySQL\mySQL
	 */
	public $sql;

	/**
	 * The name of the non-standard database.
	 * Used by generic methods.
	 * @string
	 */
	public ?string $db = NULL;

	/**
	 * @var User
	 */
	public $user;
	/**
	 * @var PA
	 */
	public $pa;

	function __construct()
	{
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
		$class_array = explode("\\", get_called_class());
		if(in_array(end($class_array), ["User"])){
			return true;
		}

		$this->user = new User();

		return true;
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
	function setAttr($a = NULL)
	{
		if(!is_array($a)){
			return true;
		}
		foreach($a as $key => $val){
			$method = str::getMethodCase("set_{$key}");
			if(method_exists($this, $method)){
				//if a custom setter method exists, use it
				$this->$method($val);
			} else {
				$this->$key = $val;
			}
		}
		return true;
	}

	/**
	 * Checks the $a array for given vars keys.
	 * If they don't exists, throws an exception.
	 *
	 * Should only be used in cases where a missing key
	 * suggests tampering or a bug in the code.
	 *
	 * @param array $a The $a array.
	 * @param string|array $keys Can either be a single key (as a string), or an array of keys.
	 *
	 * @throws BadRequest
	 */
	public function checkVars(array $a, $keys): void
	{
		extract($a);

		if(is_string($keys)){
			$keys = [$keys];
		}

		foreach($keys as $key){
			if(!is_array($vars) || !key_exists($key, $vars)){
				throw new BadRequest(\App\Common\str::title("You must supply a <code>{$key}</code> to this method."));
			}

			if(!$vars[$key]){
				throw new BadRequest(\App\Common\str::title("The <code>{$key}</code> value cannot be empty."));
			}
		}
	}

	/**
	 * Shortcut for complex, repeated SQL queries. Simple syntax:
	 * <code>
	 * $rel = $this->info($rel_table, $rel_id, $refresh);
	 * </code>
	 * alternatively, add more colour:
	 * <code>
	 * $rel = $this->info([
	 *    "rel_table" => $rel_table,
	 *    "rel_id" => $rel_id,
	 *    "where" => [
	 *        "key" => "val"
	 *    ]
	 * ], NULL, $refresh);
	 * </code>
	 *
	 * @param             $rel_table_or_array
	 * @param string|null $rel_id
	 * @param null        $refresh
	 *
	 * @return array|null
	 * @throws Exception
	 */
	protected function info($rel_table_or_array, ?string $rel_id = NULL, $refresh = NULL): ?array
	{
		$info = Info::getInstance();
		return $info->getInfo($rel_table_or_array, $rel_id, (bool)$refresh);
	}

	/**
	 * Get and set permissions for users.
	 * @return Permission
	 */
	protected function permission()
	{
		static $permission = NULL;
		if(!$permission){
			$permission = new Permission();
		}
		return $permission;
	}

	/**
	 * Shortcut for the user->accessDenied method.
	 *
	 * @param array|null $a The current URL (for the callback)
	 *
	 * @return bool
	 */
	function accessDenied(?array $a = NULL): bool
	{
		return $this->user->accessDenied($a);
	}

	/**
	 * Reorders items.
	 * Checks credentials, then sends user off to
	 * generic setOrder method.
	 *
	 * @param $a
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function reorder($a): bool
	{
		extract($a);

		if(!$this->permission()->get($rel_table, $rel_id, "u")){
			return $this->accessDenied();
		}

		$this->setOrder($a);

		return true;
	}

	/**
	 * Given a table name, will return the
	 * next order number.
	 *
	 * @param string      $rel_table
	 *
	 * @param string|null $limiting_key Use the limiting key/val to limit the relevant rows
	 * @param string|null $limiting_val The key/val should be a column that uniquely identifies the subset of interest
	 *
	 * @return bool|int
	 * @throws Exception
	 */
	public function getOrder(string $rel_table, ?string $limiting_key = NULL, ?string $limiting_val = NULL)
	{
		# Ensure the table has an order column
		if(!in_array("order", array_keys($this->sql->getTableMetadata(["db" => $this->db, "name" => $rel_table], true)))){
			return false;
		}

		# Get the highest order
		$$rel_table = $this->sql->select([
			"db" => $this->db,
			"columns" => [
				"max_order" => ["max", "order"],
			],
			"table" => $rel_table,
			"where" => [
				$limiting_key => $limiting_val
			],
			"limit" => 1,
		]);

		# Return the number one higher
		return ${$rel_table}['max_order'] + 1;
	}

	/**
	 * Reorders items in a table.
	 * Will reorder the entire table.
	 *
	 * To avoid reordering the entire table,
	 * set the limiting_key/val pair,
	 * a key/val that uniquely identifies
	 * all the items being reordered.
	 *
	 * Its new order is a number that it will have
	 * all by itself. All the other items
	 * that occupy that number or higher/lower
	 * will be pushed up or down to make space.
	 *
	 * Broken up so that authentication and action
	 * are separated out. While this is a public
	 * method, as it returns void, any direct
	 * access attempts will fail.
	 *
	 * @param array $a
	 * @param null  $silent
	 *
	 * @throws Exception
	 */
	public function setOrder(array $a, $silent = NULL): void
	{
		extract($a);

		if($vars['order']){
			$push = 1;
			foreach($vars['order'] as $original_order => $id){
				$order = $original_order + $push;
				while($this->sql->select([
					"count" => true,
					"table" => $rel_table,
					"where" => [
						"order" => $order,
						$vars['limiting_key'] => $vars['limiting_val'],
					]
				])){
					//if the order already exists, jump one
					$push++;
					$order = $original_order + $push;
				}
				$this->sql->update([
					"table" => $rel_table,
					"id" => $id,
					"set" => [
						"order" => $order
					]
				]);
			}
			if(!$silent){
				$this->log->success([
					"icon" => "random",
					"message" => "Reordered",
				]);
			}
			return;
		}

		# No change to order
		if($vars['old_index'] == $vars['new_index']){
			//Nothing to do
			return;
		}

		if($vars['old_index'] > $vars['new_index']){
			//going UP (towards 1)
			$dir1 = "+";
			$eq1 = ">=";
			//All the items equal or greater than the new order are to be moved down
			$dir2 = "-";
			$eq2 = ">";
			//All the greater than the old order are to be moved up (to fill the gap)
		} else {
			//going DOWN (away from 1)
			$dir1 = "-";
			$eq1 = "<=";
			//All the items equal to or less than the new order are to be moved up
			$dir2 = "+";
			$eq2 = "<";
			//All the items less than the old order are to be moved down (to fill the gap)
		}

		# Get the _current_ (soon to be old) order number
		$old = $this->sql->select([
			"columns" => [
				"order"
			],
			"db" => $this->db,
			"table" => $rel_table,
			"id" => $rel_id
		]);
		$old['order'] = $old['order'] ?: "0";

		# The key of the item being shifted
		$rel_key = array_search($rel_id, $vars['order']);

//		# Ensure there are no rows with NULL order values
//		$this->sql->run("SET @a=1;");
//		$this->sql->update([
//			"db" => $this->db,
//			"table" => $rel_table,
//			"set" => [
//				["`order` = @a:=@a+1"],
//			],
//			"where" => [
//				$vars['limiting_key'] => $vars['limiting_val'],
//				"order" => NULL,
//			],
//		]);

		if(!$rel_key){
			//if the item is being moved to the first (0) position
			$new_order = 1;
		} else if(($rel_key + 1) == count($vars['order'])){
			//if the item is being moved to the _last_ position
			$item_above = $this->info($rel_table, $vars['order'][$rel_key - 1]);
			$new_order = (int)$item_above['order'];
		} else {
			//if the item is in any position except the first (0)
			$item_above = $this->info($rel_table, $vars['order'][$rel_key - 1]);
			$new_order = (int)$item_above['order'] + 1;
		}

		$new_order = $new_order ?: "0";

		# Place the item in its new position
//		echo
		$this->sql->update([
			"db" => $this->db,
			"table" => $rel_table,
			"id" => $rel_id,
			"set" => [
				"order" => $new_order,
			],
		], false);
//		echo ";\r\n\r\n";

		# Push all other items up/down to make space for our item
//		echo
		$this->sql->update([
			"db" => $this->db,
			"table" => $rel_table,
			"set" => [
				"order" => [$this->db, $rel_table, "order", "IFNULL(", ",{$new_order}) {$dir1} 1"],
			],
			"where" => [
				$vars['limiting_key'] => $vars['limiting_val'],
				["order", $eq1, $new_order],
				["{$rel_table}_id", "<>", $rel_id]
			],
		], false);
//		echo ";\r\n\r\n";

		/**
		 * As a consequence of the above update,
		 * Some elements that were below/above the old order,
		 * that _shouldn't_ have been pushed, were also pushed
		 */
//		echo
		$this->sql->update([
			"db" => $this->db,
			"table" => $rel_table,
			"set" => [
				"order" => [$this->db, $rel_table, "order", "IFNULL(", ",{$old['order']}) {$dir2} 1"],
			],
			"where" => [
				$vars['limiting_key'] => $vars['limiting_val'],
				["order", $eq2, $old['order']],
				["{$rel_table}_id", "<>", $rel_id]
			],
		], false);
//		echo ";\r\n\r\n";

		if(!$silent){
			$this->log->success([
				"icon" => "random",
				"message" => "Reordered",
			]);
		}
	}


	/**
	 * Finds the Field class and the relevant method for this rel_table.
	 * Checks if they exist. Will error out of they don't.
	 *
	 * @param $rel_table
	 *
	 * @return array
	 * @throws \ReflectionException
	 */
	protected function getFieldClassAndMethod($rel_table): array
	{
		$current_class = get_class($this);
		$reflection_class = new ReflectionClass($current_class);
		$namespace = $reflection_class->getNamespaceName();
		$field_class = $namespace . "\\Field";
		$method = str::getMethodCase($rel_table);

		if(!class_exists($field_class)){
			throw new Exception("A <code>Field()</code> class for the <b>{$rel_table}</b> table could not be found.");
		}

		if(!method_exists($field_class, $method)){
			throw new Exception("A <code>{$method}</code> method could not be found in the <code>{$field_class}</code> class.");
		}

		return [$field_class, $method];
	}

	/**
	 * VERY generic updateRelTable method. Relies on the rowHandler method
	 * to format each line.
	 *
	 * Should ONLY be used for testing, beacuse it will pull the entire table.
	 *
	 * @param array $a
	 *
	 * @throws Exception
	 */
	public function updateRelTable(array $a): void
	{
		extract($a);

		$$rel_table = $this->info($rel_table);

		if($$rel_table){
			foreach($$rel_table as $row){
				$rows[] = $this->rowHandler($row, $a);
			}
		} else {
			$rows[] = Table::emptyTablePlaceholder($rel_table);
		}

		$table = [
			"name" => $rel_table,
			"db" => $this->db,
		];

		if(in_array("order", array_keys($this->sql->getTableMetadata($table, true)))){
			//if this table has an order-by column
			$this->output->update("#all_{$rel_table}", Table::generate($rows, [
				"rel_table" => $rel_table,
				"order" => true,
			]));
			return;
		}

		$this->output->update("#all_{$rel_table}", Table::generate($rows));
	}

	/**
	 * A _very_ generic rowHandler method, should be switched with a custom method.
	 * Is public so that other classes can access it.
	 *
	 * @param array $cols
	 *
	 * @return array
	 */
	public function rowHandler(array $cols, ?array $a = []): array
	{
		extract($a);
		$cols_user_cannot_update = $this->sql->getTableColumnsUsersCannotUpdate($rel_table);

		foreach($cols as $col => $val){
			if(in_array($col, $cols_user_cannot_update)){
				continue;
			}
			if($col == "order"){
				$row["id"] = $cols["{$rel_table}_id"];
				continue;
			}
			$row[str::title($col)] = [
				"html" => $val,
			];
			if($col == "title"){
				$row[str::title($col)]['alt'] = str::title("Edit this {$rel_table}");
				$row[str::title($col)]['hash'] = [
					"rel_table" => $rel_table,
					"rel_id" => $cols["{$rel_table}_id"],
					"action" => "edit",
				];
			}
		}

		$row[""] = [
			"sortable" => false,
			"sm" => 2,
			"button" => $this->getRowButtons($cols, $a),
		];

		return $row;
	}

	/**
	 * Generic set of "make public", "edit" and "remove" buttons.
	 *
	 * @param array $cols
	 * @param array $a
	 *
	 * @return array
	 */
	public function getRowButtons(array $cols, ?array $a = []): array
	{
		extract($a);

		if(key_exists("public", $cols)){
			if($cols['public']){
				$button[] = [
					"alt" => str::title("Remove {$rel_table} from public view"),
					"icon" => "store",
					"colour" => "success",
					"size" => "s",
					"hash" => [
						"rel_table" => $rel_table,
						"rel_id" => $cols["{$rel_table}_id"],
						"action" => "update",
						"vars" => [
							"public" => 0,
						],
					],
				];
			} else {
				$button[] = [
					"alt" => str::title("Make {$rel_table} public"),
					"icon" => "store",
					"colour" => "success",
					"basic" => true,
					"size" => "s",
					"hash" => [
						"rel_table" => $rel_table,
						"rel_id" => $cols["{$rel_table}_id"],
						"action" => "update",
						"vars" => [
							"public" => 1,
						],
					],
				];
			}
		}

		$button[] = [
			"size" => "s",
			"hash" => [
				"rel_table" => $rel_table,
				"rel_id" => $cols["{$rel_table}_id"],
				"action" => "edit",
			],
			"icon" => Icon::get("edit"),
			"basic" => true,
		];

		$button[] = [
			"size" => "s",
			"hash" => [
				"rel_table" => $rel_table,
				"rel_id" => $cols["{$rel_table}_id"],
				"action" => "remove",
			],
			"approve" => [
				"icon" => Icon::get("trash"),
				"colour" => "red",
				"title" => str::title("Remove {$rel_table}?"),
				"message" => str::title("Are you sure you want to remove this {$rel_table}?"),
			],
			"icon" => Icon::get("trash"),
			"basic" => true,
			"colour" => "danger",
		];

		return $button;
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
	//			$this->alert->success([
	//				"icon" => "indent",
	//				"title" => str::title("Great success!"),
	//				"message" => str::title("Created a new {$rel_table} record.")
	//			]);
	//		}
	//
	//		return $insert_id;
	//	}
}