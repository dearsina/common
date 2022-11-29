<?php


namespace App\Common;

use API\Microsoft\Azure\Azure;
use App\Common\Exception\BadRequest;
use App\Common\Permission\Permission;
use App\Common\SQL\Factory;
use App\Common\SQL\Info\Info;
use App\Common\User\User;
use App\UI\Button;
use App\UI\Icon;
use App\UI\Table;
use Exception;
use ReflectionClass;

/**
 * Class Common
 * The parent class for both ModalPrototype and CardPrototype.
 * @package App\Common
 */
abstract class Prototype {
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
	 * @var SQL\Info\Info
	 */
	public $info;

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
		 *
		 * So far, that's only the App\Common\User\User class
		 */
		if(in_array(get_called_class(), ['App\Common\User\User'])){
			return;
		}

		$this->user = new User();

		return;
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
			}
			else {
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
	 * @param array        $a    The $a array.
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
				throw new BadRequest(str::title("You must supply a <code>{$key}</code> to this method."));
			}

			if(!$vars[$key]){
				throw new BadRequest(str::title("The <code>{$key}</code> value cannot be empty."));
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
	 * ], NULL, $refresh, ["joins"]);
	 * </code>
	 *
	 * @param             $rel_table_or_array
	 * @param string|null $rel_id
	 * @param null        $refresh
	 * @param array|null  $joins
	 *
	 * @return array|null
	 * @throws Exception
	 */
	protected function info($rel_table_or_array, ?string $rel_id = NULL, $refresh = NULL, ?array $joins = NULL): ?array
	{
		if(!$this->info){
			$this->info = Info::getInstance();
		}
		return $this->info->getInfo($rel_table_or_array, $rel_id, (bool)$refresh, $joins);
	}

	/**
	 * Get and set permissions for users.
	 * @return Permission
	 */
	protected function permission()
	{
		static $permission = NULL;
		if(!$permission){
			if(!$classPath = str::findClass("Permission")){
				throw new \Exception("Cannot find a suitable Permissions class.");
			}
			$permission = new $classPath();
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
	public function reorder(array $a): bool
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
				$limiting_key => $limiting_val,
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

		$this->checkVars($a, "order");

		$push = 1;
		foreach($vars['order'] as $original_order => $id){
			$order = $original_order + $push;
			while($this->sql->select([
				"count" => true,
				"table" => $rel_table,
				"where" => [
					"order" => $order,
					$vars['limiting_key'] => $vars['limiting_val'],
				],
			])) {
				//if the order already exists, jump one
				$push++;
				$order = $original_order + $push;
			}
			$this->sql->update([
				"table" => $rel_table,
				"id" => $id,
				"set" => [
					"order" => $order,
				],
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
	 * Should ONLY be used for testing, because it will pull the entire table.
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
		}
		else {
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

		if(!$rel_table){
			throw new \Exception("The row handler doesn't have enough metadata to go on. Make sure you feed it at least a rel_table.");
		}

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
	public function getRowButtons(array $cols, ?array $a = []): ?array
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
			}
			else {
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

		$button[] = Button::generic("edit", $rel_table, $cols["{$rel_table}_id"]);

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

	/**
	 * Given a rel_table/id, will translate the rel_table to a
	 * `rel_table_id` column name and remove all rows from
	 * all tables with that column name and the corresponding
	 * rel_id.
	 *
	 * Should be used with great caution
	 *
	 * @param string    $rel_table
	 * @param string    $rel_id
	 * @param bool|null $silent
	 *
	 * @throws Exception
	 */
	public function removeFromAllTables(string $rel_table, string $rel_id, ?bool $silent = NULL): void
	{
		# The column name is always table + _id
		$column_name = "{$rel_table}_id";

		# Look through all tables across all DBs for this column name
		$results = $this->sql->run("
			SELECT
			    -- Distinct because we're only interested in one row per table name (and we're hitting the column table)
				DISTINCT
				`INFORMATION_SCHEMA`.`COLUMNS`.`TABLE_SCHEMA`,
				`INFORMATION_SCHEMA`.`COLUMNS`.`TABLE_NAME` 
			FROM `INFORMATION_SCHEMA`.`COLUMNS`
			
			-- Left join the views table to exclude any views
			LEFT JOIN `INFORMATION_SCHEMA`.`VIEWS`
            ON `INFORMATION_SCHEMA`.`VIEWS`.`TABLE_SCHEMA` = `INFORMATION_SCHEMA`.`COLUMNS`.`TABLE_SCHEMA`
            AND `INFORMATION_SCHEMA`.`VIEWS`.`TABLE_NAME` = `INFORMATION_SCHEMA`.`COLUMNS`.`TABLE_NAME`
			
			WHERE 0=0
		    -- We're not interested in views
            AND `INFORMATION_SCHEMA`.`VIEWS`.`VIEW_DEFINITION` IS NULL
			-- We're interested in all tables except the cache tables
			AND `INFORMATION_SCHEMA`.`COLUMNS`.`TABLE_SCHEMA` <> 'cache'
			-- Where the column name appears
			AND `INFORMATION_SCHEMA`.`COLUMNS`.`COLUMN_NAME` = '{$column_name}';");

		if(!$results['num_rows']){
			throw new \Exception("Could not find any tables with the <code>{$column_name}</code> column. Are you sure you got the right name?");
		}

		foreach($results['rows'] as $table){
			if(!$count = $this->sql->select([
				"db" => $table['TABLE_SCHEMA'],
				"count" => true,
				"table" => $table['TABLE_NAME'],
				"where" => [
					$column_name => $rel_id,
				],
			])){
				continue;
			}
			$table_counts[$table['TABLE_NAME']] = $count;

			$this->sql->remove([
				"db" => $table['TABLE_SCHEMA'],
				"table" => $table['TABLE_NAME'],
				"where" => [
					$column_name => $rel_id,
				],
			]);
		}

		# Delete all files belonging to this rel ID
		$azure = new Azure();
		$blob_count = $azure->getBlobCount($rel_id);
		$azure->deleteContainer($rel_id);

		if($table_counts){
			foreach($table_counts as $table => $count){
				$table_narratives[] = str::title(str::pluralise_if($count, "row", true) . " from the <b>{$table}</b> table");
			}
			$narrative[] = str::oxfordImplode($table_narratives) . " were deleted.";
		}

		$narrative[] = " From the cloud, " . str::were($blob_count, "file", true) . " deleted.";

		if(!$silent){
			$this->log->info([
				"icon" => Icon::get("remove"),
				"message" => implode(" ", $narrative),
			]);
		}
	}

	/**
	 * Given a rel_table/id, will translate the rel_table to a
	 * `rel_table_id` column name and DELETE all rows from
	 * all tables with that column name and the corresponding
	 * rel_id.
	 *
	 * Should be used in very carefully.
	 *
	 * @param string    $rel_table
	 * @param string    $rel_id
	 * @param bool|null $silent
	 *
	 * @throws Exception
	 */
	public function deleteFromAllTables(string $rel_table, string $rel_id, ?bool $silent = NULL): void
	{
		# The column name is always table + _id
		$column_name = "{$rel_table}_id";

		# Look thru all tables for this column name
		$results = $this->sql->run("SELECT DISTINCT TABLE_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE COLUMN_NAME IN ('{$column_name}') AND TABLE_SCHEMA='{$_ENV['db_database']}' ORDER BY TABLE_NAME DESC;");

		if(!$results['num_rows']){
			throw new \Exception("Could not find any tables with the <code>{$column_name}</code> column. Are you sure you got the right name?");
		}

		foreach($results['rows'] as $table){
			if($table['TABLE_NAME'] == $rel_table){
				continue;
			}
			if(!$count = $this->sql->select([
				"count" => true,
				"table" => $table['TABLE_NAME'],
				"where" => [
					$column_name => $rel_id,
				],
				"include_removed" => true,
			])){
				continue;
			}
			$table_counts[$table['TABLE_NAME']] = $count;

			$this->sql->delete([
				"table" => $table['TABLE_NAME'],
				"where" => [
					$column_name => $rel_id,
				],
				"include_removed" => true,
			]);
		}

		# Otherwise it will fail on foreign key constraints
		$this->sql->delete([
			"table" => $rel_table,
			"id" => $rel_id,
		]);
		$table_counts[$rel_table] = 1;

		# Delete all files belonging to this rel ID
		$azure = new Azure();
		$blob_count = $azure->getBlobCount($rel_id);
		$azure->deleteContainer($rel_id);

		foreach($table_counts as $table => $count){
			$table_narratives[] = str::title(str::pluralise_if($count, "row", true) . " from the <b>{$table}</b> table");
		}

		$narrative = str::oxfordImplode($table_narratives) . " were deleted.";
		$narrative .= " From the cloud, " . str::were($blob_count, "file", true) . " deleted.";

		if(!$silent){
			$this->log->info([
				"icon" => Icon::get("remove"),
				"message" => $narrative,
			]);
		}
	}

	/**
	 * Given a rel_table/id will check if the item was removed,
	 * and if so, return the record with metadata.
	 *
	 * <code>
	 * if($removed = $this->getRemoved($rel_table, $rel_id)){
	 *    throw new BadRequest("This item was removed by {$removed['user']['name']} " . str::ago($removed['removed']) . ".");
	 * }
	 * </code>
	 *
	 * @param string $rel_table
	 * @param string $rel_id
	 *
	 * @return array|null
	 */
	public function getRemoved(string $rel_table, string $rel_id): ?array
	{
		if(!$removed = $this->sql->select([
			"table" => $rel_table,
			"left_join" => [[
				"table" => "user",
				"on" => [
					"user_id" => [$rel_table, "removed_by"]
				],
				"include_removed" => true,
			]],
			"id" => $rel_id,
			"where" => [
				["removed", "IS NOT", NULL],
			],
			"include_removed" => true,
			"include_meta" => true,
		])){
			// If a removed record cannot be found
			return NULL;
		}

		str::flattenSingleChildren($removed, ["user"]);

		if($removed['user']){
			# Format user name
			str::addNames($removed['user']);
		}

		else {
			# Ensure there is always a user->name key value
			$removed['user']['name'] = "the system";
		}

		return $removed;
	}

	/**
	 * Given an $a array, and an array of keys,
	 * remove all the key values from the $a vars, and only
	 * set one key value back, the first one with a value.
	 *
	 * Example: If  keys a, b, c are sent, and b and c have
	 * a value, only the b key and value will be kept.
	 *
	 * @param array $a
	 * @param array $keys
	 */
	public function onlyFirstKeyWithValue(array &$a, array $keys): void
	{
		extract($a);

		foreach($keys as $key){
			$a['vars'][$key] = NULL;
		}

		foreach($keys as $key){
			if($vars[$key]){
				$a['vars'][$key] = $vars[$key];
				return;
			}
		}
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