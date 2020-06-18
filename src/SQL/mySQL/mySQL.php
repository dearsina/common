<?php

namespace App\Common\SQL\mySQL;

use App\Common\str;

use Ramsey\Uuid\Codec\TimestampFirstCombCodec;
use Ramsey\Uuid\Generator\CombGenerator;
use Ramsey\Uuid\UuidFactory;

/**
 * Reset global vars related to SQL calls.
 * Mostly used for bug tracking.
 */
$_SESSION['database_calls'] = 0;
$_SESSION['queries'] = [];

/**
 * Class mySQL
 * An update to the sql() class.
 *
 * @package App\Common
 * @version 3
 */
class mySQL extends Grow {
	/**
	 * @var \mysqli
	 */
	private $mysqli;

	/**
	 * @var mySQL
	 */
	private static $instance;

	/**
	 * A private array of table column name definitions, so that multiple calls to the same table definition doesn't result in multiple database calls.
	 * @var array
	 */
	private $tableColumnNames;
	private $tableColumnNamesAll;

	/**
	 * Connects to the SQL server, runs settings queries
	 * The constructor is private so that the class can be run in static mode
	 *
	 */
	private function __construct()
	{
		$driver = new \mysqli_driver();
		$driver->report_mode = MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR;

		try {
			# Connect to the mySQL server
			$this->mysqli = new \mysqli($_ENV['db_servername'],$_ENV['db_username'], $_ENV['db_password'], $_ENV['db_database']);

			# Ensure everything is UTF8mb4
			$this->mysqli->set_charset('utf8mb4');

			# Ensure PHP and mySQL time zones are in sync
			$offset = (new \DateTime())->format("P");
			$this->mysqli->query("SET time_zone='$offset';");

			$this->loadTableMetadata();
		}

		catch(\mysqli_sql_exception | \Exception $e) {
			/**
			 * If there is an error connecting,
			 * put together a custom response,
			 * and stop everything, because
			 * without a database connection,
			 * nothing can be done.
			 */
			echo json_encode([
				"success" => false,
				"alerts" => [[
					"container" => "#ui-view",
					"close" => false,
					"type" => "error",
					"title" => "SQL Connection error",
					"icon" => "far fa-ethernet",
					"message" => $e->getMessage()
				]]
			]);
			exit();
			//There is no point in continuing
		}
	}

	private function __clone() {
		// Prevent the cloning of the object
	}

	private function __wakeup() {
		// Stopping unserializing of object
	}

	/**
	 * @return mySQL
	 */
	public static function getInstance() {
		// Check if instance is already exists
		if (self::$instance == null) {
			self::$instance = new mySQL();
		}

		return self::$instance;
	}

	/**
	 * Loads a complete index of metadata for
	 * all the tables in the app database.
	 * This index is used by every query to determine
	 * if certain column values can be CRUD by the user.
	 *
	 * @return bool
	 */
	private function loadTableMetadata(){
		# Get all column names
		$result = $this->run("SELECT * FROM `INFORMATION_SCHEMA`.`COLUMNS` WHERE `TABLE_SCHEMA` = 'app'");

		# Make sure tables exist
		if(!$result['rows']){
			throw new \mysqli_sql_exception("No tables exist for this database.");
		}

		# Fill the two array with the appropriate column definitions
		foreach ($result['rows'] as $c) {
			$this->tableColumnNamesAll[$c['TABLE_NAME']][$c['COLUMN_NAME']] = $c;
			if (!in_array($c['COLUMN_NAME'], $this->getTableColumnsUsersCannotUpdate($c['TABLE_NAME']))) {
				$this->tableColumnNames[$c['TABLE_NAME']][$c['COLUMN_NAME']] = $c;
			}
		}

		return true;
	}

	/**
	 * Binary question, does this table exist?
	 *
	 * @param string $table Name of a table to check.
	 *
	 * @return bool TRUE on true, FALSE on false, no error messages.
	 */
	public function tableExists($table){
		$table = str::i($table);
		$result = $this->run("SELECT * FROM `INFORMATION_SCHEMA`.`COLUMNS` WHERE `TABLE_SCHEMA` = 'app' AND `TABLE_NAME` = '{$table}'");
		return !empty($result['rows']);
	}

	/**
	 * Binary question, does this column in this table exist?
	 *
	 * @param string $table  Name of the table to check.
	 * @param string $column Name of the column in the table to check.
	 * @param bool   $all If set to true, will check _all_ columns, not just the ones the user can update (Default is TRUE)
	 *
	 * @return bool TRUE on true, FALSE on false, no error messages.
	 */
	public function columnExists($table, $column, $all = TRUE){
		$table = str::i($table);
		$column = str::i(str_replace("`", "", $column));
		return in_array($column, array_column($this->getTableMetadata($table, $all), "COLUMN_NAME"));
//		$result = $this->run(/**@lang MySQL */ "
//			SELECT * FROM `INFORMATION_SCHEMA`.`COLUMNS`
//			WHERE 0=0
//			AND `TABLE_SCHEMA` = 'app'
//			AND `TABLE_NAME` = '{$table}'
//			AND `COLUMN_NAME` = '{$column}'
//		");
//		return !empty($result['rows']);
	}

	/**
	 * Reconnects to a mySQL server.
	 * Can be run even if the connection is still active.
	 *
	 * This is only really useful for when long running
	 * scripts that can stay dormant for extended periods
	 * of time want to reconnect to the mySQL server.
	 *
	 * Set the reconnect flag to true to initiate.
	 *
	 * @return bool
	 */
	private function reconnect(){
		try {
			if($this->mysqli->ping()){
				return true;
			}
			return true;
		}
		catch (\mysqli_sql_exception $e){
			if (self::__construct()) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Runs a given SQL query, returns an array of results or boolean FALSE with error message.
	 * Return array:
	 * <code>
	 * [
	 * 	"rows", //the result rows (if any)
	 * 	"columns", //the column headers
	 * 	"query", //the query ran
	 * 	"insert_id", //if a row has been inserted, the newly inserted row's ID
	 * 	"rowcount", //the number of rows in the result
	 * 	"affected_rows", //if update, the number of rows affected
	 * ];
	 * </code>
	 *
	 * @param $query
	 *
	 * @return array|bool
	 */
	function run($query) {

		# Ensure a mySQLi driver has been initiated
		if (!$this->mysqli) {
			if (!self::__construct()) {
				return false;
			}
		}

		# Store the query in a session variable
		$_SESSION['query'] = $query;

		if(str::runFromCLI()){
			//When run from the command line, the session super global doesn't work
			global $SESSION;
			$SESSION['query'] = $query;
			$SESSION['queries'][] = str_replace(["\r\n"]," ",$query);
		}

//		echo str_replace(["\r\n"]," ",$query)."\r\n";

		# Run the query and store the results in an array
		$result = $this->mysqli->query($query);

		if(is_object($result)){
			while ($row = $result->fetch_assoc()) {
				$rows[] = $row;
			}
			$result->close();
			//Frees the memory associated with a result
		}

		# Normalise dot-notation
		$rows = $this->normalise($rows);

		# Get columns
		$columns = is_array($rows) ? array_keys(reset($rows)) : NULL;

		# Store the number of database calls and a list of *all* queries run during this AJAX call
		$_SESSION['database_calls']++;
		$_SESSION['queries'][] = str_replace(["\r\n"]," ",$query);

		# Get (potential/optional) rowcount
		if(is_array($rows)){
			$rowcount = count($rows);
		} else {
			$result = $this->mysqli->query("SELECT ROW_COUNT();");
			if(is_object($result)) {
				$rowcount = $result->fetch_assoc()['ROW_COUNT()'];
				$result->close();
			}
		}

		# Return a meta array of results
		return [
			"rows" => $rows,
			"columns" => $columns,
			"query" => $query,
			"rowcount" => $rowcount,//is_array($rows) ? count($rows) : $this->mysqli->query("SELECT ROW_COUNT();")->fetch_assoc()['ROW_COUNT()'],
			"affected_rows" => $this->mysqli->affected_rows
		];
	}

	/**
	 * Frees up memory as the queries session variable is cleared.
	 * The queries session variable stores every single query run.
	 *
	 * @return bool
	 */
	public function freeUpMemory(){
		unset($_SESSION['queries']);
		return true;
	}

	/**
	 * Converts a flat dot-notation array to a multi-dimensional array,
	 * where each sub-level is a numerical array. Magic.
	 *
	 * Row order is important, grouping is done by comparing one row to the next (only).
	 *
	 * @param $rows
	 *
	 * @return mixed
	 */
	public function normalise($rows){
		if(!is_array($rows)){
			return $rows;
		}

		if($this->flat){
			//If the query results are to remain flat
			return $rows;
		}

//		$this->expand($rows);
		$rows = $this->expand($rows);
		$this->contract($rows);//if($this->table['name'] == 'error_log'){var_dump($rows);exit;}
		$this->prune($rows);
		$this->resetKeys($rows);

		return $rows;
	}

	/**
	 * The normalising functions create irregular key orders.
	 * This method resets all the numerical key to be incremental,
	 * starting from zero as expected.
	 *
	 * @param $array
	 *
	 * @return bool
	 */
	private function resetKeys(&$array) {
		$numberCheck = false;
		if(!is_array($array)){
			return true;
		}
		foreach ($array as $k => $val) {
			if (is_array($val)) {
				$this->resetKeys($array[$k]);
			}
			if (is_int($k)) {
				$numberCheck = true;
			}
		}
		if ($numberCheck === true) {
			$array =  array_values($array);
		}

		return true;
	}

	/**
	 * Prunes array branches that are empty, as a result of unmatched left joins.
	 *
	 * @param $array
	 */
	private function prune(&$array){
		if(is_array($array)){
			foreach($array as $key => $val){
				if(is_array($val)){
					foreach($val as $k => $v){
						if(is_array($v)){
							$this->prune($val[$k]);
						}
					}
					foreach($val as $k => $v){
						if(!empty($v)){
							//If one of the array keys has a value
							$array[$key] = $val;
							//Update the root array with the new values
							//that exclude any empty children
							continue 2;
						}
					}
					unset($array[$key]);
				}
			}
		}
		$array = empty($array) ? NULL : $array;
	}

	/**
	 * Breaks a single row of dot-notation data to a multi-dimensional array.
	 *
	 * @param array  $items
	 * @param string $delimiter
	 *
	 * @return array
	 * @link https://gist.github.com/wgrafael/8a9bb1a963042bc88dac
	 */
	private function expand(array $items, $delimiter = '.') {
		$new = [];
		foreach ($items as $key => $value) {
			if (strpos($key, $delimiter) === false) {
				$new[$key] = is_array($value) ? $this->expand($value, $delimiter) : $value;
				continue;
			}

			$segments = explode($delimiter, $key);
			$last = &$new[$segments[0]];
			if (!is_null($last) && !is_array($last)) {
				throw new \LogicException(sprintf("The '%s' key has already been defined as being '%s'", $segments[0], gettype($last)));
			}

			foreach ($segments as $k => $segment) {
				if ($k != 0) {
					if(is_string($last)){
						/**
						 * At times, a column may have the same name
						 * as the table it's being joined with.
						 */
						$last = [];
						/**
						 * In those cases, ignore the column value.
						 */
					}
					$last = &$last[$segment];
				}
			}
			$last = is_array($value) ? $this->expand($value, $delimiter) : $value;
		}
		return $new;
	}

	/**
	 * Attempts to group rows of multi-dimensional data together.
	 * Looks at all the non-array vales and groups the same values together,
	 * then looks at their children and performs the same comparison iteratively.
	 * The grouping is done by comparing one row to the following (only),
	 * thus row order is crucial to identify rows that belong together.
	 *
	 * The comparison of two rows requires the rows to have a rel_table_id column.
	 * Without it, the two rows could just coincidentally be the same, thus are not
	 * contracted together.
	 *
	 * @param      $data
	 * @param null $table
	 *
	 * @return bool
	 */
	private function contract(&$data, $table = NULL){
		$table = $table ?: $this->table['name'];

		$nextLevel = [];
		$mergedNextLevel = [];

		# Break apart next level
		foreach($data as $id => $row){
			foreach($row as $key => $val){
				if(is_array($val)){
					$nextLevel[$id][$key] = $val;
					unset($data[$id][$key]);
				}
			}
		}

		# Merge rows at the current level that belong together (have the same key/values)
		foreach($data as $id => $row){
			if(!in_array("{$table}_id", array_keys($row))){
				//Do nothing?
			} else if($id && ($originalId = array_search(serialize($row), $serialisedRows)) !== FALSE) {
				$mergedNextLevel[$originalId][$id] = $nextLevel[$id];
				unset($data[$id]);
				continue;
			}
			$serialisedRows[$id] = serialize($row);
			if(array_key_exists($id, $nextLevel)){
				$mergedNextLevel[$id][$id] = $nextLevel[$id];
			}
		}

		# Bring together
		foreach($mergedNextLevel as $id => $levels){
			foreach($levels as $rowId => $row){
				if(!is_array($row)){
					continue;
				}
				foreach($row as $key => $val){
					# Check to see that the level being added actually contains values
					$empty = true;
					foreach($val as $k => $v){
						if($v){	$empty = false;	break;}
						//If a key has a value, the level has value
					}
					if($empty){
						//if the entire level doesn't have value
						$data[$id][$key] = NULL;
						//Set the whole table as NULL
						//This way, the key is retained, but the value is removed
					} else {
						$data[$id][$key][] = $val;
					}
				}
			}
		}

		# Go deeper
		foreach($data as $id => $row){
			foreach($row as $key => $val){
				if(is_array($val)){
					$this->contract($data[$id][$key], $key);
				}
			}
		}

		return true;
	}

	private $where;
	private $or;

	/**
	 * Set flag to true to show removed rows also.
	 * The default is to hide any row where removed IS *NOT* NULL
	 *
	 * @param $table          string The relevant table name
	 * @param $tableAlias     string The relevant table alias
	 * @param $includeRemoved bool Set to TRUE to show removed rows
	 * @param $store          array The referenced array where the string is (potentially) stored
	 *
	 * @return bool
	 */
	private function includeRemoved($table, $tableAlias, $includeRemoved, &$store){
		if($includeRemoved){
			return true;
		}
		$store[] = [
			"col" => "removed",
			"val" => NULL,
			"tableName" => $table,
			"tableAlias" => $tableAlias,
			"not" => false
		];

		return true;
	}

	/**
	 * Returns a table (array) of metadata for a given table, on a column-per-row basis.
	 *
	 * @param        $table  string The relevant table name.
	 * @param null   $all    bool If set to TRUE will return *all* columns,
	 *                       not just the ones the user can update.
	 * @param null   $update bool If set to TRUE will refresh the columns,
	 *                       otherwise will use an existing array of column names.
	 *
	 * @return array|bool
	 */
	public function getTableMetadata($table, $all = NULL, $update = NULL){
		if(!$table){
			throw new \mysqli_sql_exception("A table name must be given.");
		}

		# Ensure table string is clean
		$table = str::i($table);

		# Take into account that on occasion, table names are from another database (?)
		$table_array = explode(".", $table);
		$table = end($table_array);

		if($update) {
			//If the user has requested an update to the index
			$this->loadTableMetadata();
		}

		if($all){
			//if the user has requested *all* columns (including those the user cannot update)
			return $this->tableColumnNamesAll[$table];
		}

		# Return only the columns they can update
		return $this->tableColumnNames[$table];
	}

	/**
	 * Returns a list of columns a user is NOT allowed to update.
	 *
	 * @param $table
	 *
	 * @return array
	 */
	public function getTableColumnsUsersCannotUpdate($table){
		return [
			"${table}_id",
			"created",
			"created_by",
			"updated",
			"updated_by",
			"removed",
			"removed_by"
		];
	}

	/**
	 * Array of the following column data
	 * <code>
	 * $this->columns[] = [
	 * 	"tableName" => $this->table['name'],
	 * 	"tableAlias" => $this->table['alias'],
	 * 	"columnName" => $columnName,
	 * 	"columnAlias" => $columnAlias,
	 * 	"columnWithAlias" => "{$columnName} AS '{$columnAlias}'"
	 * ];
	 * </code>
	 * @var
	 */
	private $columns;

	/**
	 * Boolean check to see if column name is actually a function call.
	 *
	 * @param $column
	 *
	 * @return bool
	 */
	private function isFunction($column){
		if(preg_match("/^(COUNT|MAX|MIN|AVG|SUM)\(/i", $column)) {
			//if the order by column is a function
			return true;
		}
		return false;
	}

	/**
	 * Adds columns to the $this->columns array for a given table.
	 * If the table is a joined table ($isJoin is TRUE), a column alias of table.column will be given.
	 * This is so that the column can be easily separated.
	 *
	 * Both table and column names will be enclosed with grave accents
	 * to allow for table or column names containing periods or spaces.
	 *
	 * @param      $tableName string The relevant table.
	 * @param      $tableAlias string The relevant table alias.
	 * @param null $c array|string A column name or an array of column names
	 * @param null $isJoin bool If table is being joined, set to TRUE
	 *
	 * @return bool
	 */
	private function setColumns($tableName, $tableAlias, $c = NULL, $isJoin = NULL){
		# Has a column name or an array of column names been included?
		if(is_string($c)){
			$columns[] = $c;
		} else if (is_array($c)){
			$columns = $c;
		}

		if($columns) {
			//If this table has a column definition
			foreach($columns as $columnAlias => $columnName){
				if(!is_string($columnAlias)) {
					//if the column alias is NOT used
					continue;
				}

				if($this->isFunction($columnName)){
					//if this is a function with a custom alias
					$this->needsGroupBy = true;

					# Store the users custom column + alias
					$this->columns[] = [
						"tableName" => $tableName,
						"tableAlias" => $tableAlias,
						"columnName" => $columnName,
						"columnAlias" => $columnAlias,
						"columnWithAlias" => "{$columnName} AS '{$columnAlias}'"
					];
				} else {
					//if this is a normal column but it just so happens to have a custom alias
					if($isJoin){
						$this->columns[] = [
							"tableName" => $tableName,
							"tableAlias" => $tableAlias,
							"columnName" => $columnName,
							"columnAlias" => "`{$this->tablePrefix[$tableAlias]}`.{$columnAlias}",
							"columnWithAlias" => "`{$tableAlias}`.`{$columnName}` AS '{$this->tablePrefix[$tableAlias]}.{$columnAlias}'"
						];
					} else {
						$this->columns[] = [
							"tableName" => $tableName,
							"tableAlias" => $tableAlias,
							"columnName" => $columnName,
							"columnAlias" => "`{$this->tablePrefix[$tableAlias]}`.{$columnAlias}",
							"columnWithAlias" => "`{$tableAlias}`.`{$columnName}` AS '{$columnAlias}'"
						];
					}

					unset($columns[$columnAlias]);
					//to avoid it being double called below
				}
			}
			/**
			 * At times, a user can define their own column aliases.
			 * This is particularly useful for function calls like
			 * COUNT([rel_table].column).
			 *
			 * The above foreach captures the custom column aliases.
			 */

			if (in_array("*", $columns)) {
				if (!$table_metadata = ($this->getTableMetadata($tableName, true))) {
					return false;
				}
				$useableColumns = array_column($table_metadata, "COLUMN_NAME");
			} else {
				$useableColumns = array_intersect($columns, array_column($this->getTableMetadata($tableName, true), "COLUMN_NAME"));
			}
		} else if($c === FALSE){
			//if columns have deliberately been set to false
			return true;
		} else {
			$useableColumns = array_column($this->getTableMetadata($tableName, true),"COLUMN_NAME");
		}

		foreach($useableColumns as $columnName){
			if($isJoin){
				$this->columns[] = [
					"tableName" => $tableName,
					"tableAlias" => $tableAlias,
					"columnName" => $columnName,
					"columnAlias" => "`{$this->tablePrefix[$tableAlias]}`.{$columnName}",
					//This is the one that shows up in GROUP BYs
					"columnWithAlias" => "`{$tableAlias}`.`{$columnName}` AS '{$this->tablePrefix[$tableAlias]}.{$columnName}'"
				];
			} else {
				$this->columns[] = [
					"tableName" => $tableName,
					"tableAlias" => $tableAlias,
					"columnName" => $columnName,
					"columnAlias" => "`{$this->tablePrefix[$tableAlias]}`.{$columnName}",
					"columnWithAlias" => "`{$tableAlias}`.`{$columnName}`"
				];
			}
		}

		return true;
	}

	/**
	 * Returns all the columns for the given alias.
	 *
	 * A column row contains the following values:
	 * <code>
	 * [
	 *    "tableName" => $tableName,
	 *    "tableAlias" => $tableAlias,
	 *    "columnName" => $columnName,
	 *    "columnAlias" => $columnAlias,
	 *    "columnWithAlias" => "{$columnName} AS '{$columnAlias}'"
	 * ];
	 * </code>
	 *
	 * @param bool         $SQL                If set will return a comma separated string of columns and their aliases, otherwise the array values will be returned.
	 * @param string|array $thisTableAliasOnly If set will include columns belonging to the following table(s) only.
	 * @param string|array $ignoreTableAlias   If set will exclude columns belonging to the following table(s) only.
	 * @param string|array $useColumnAliasesOnly If set to true, will only use column aliases (and not column *names*)
	 *
	 * @return array|bool|string
	 */
	private function getColumns($SQL = NULL, $thisTableAliasOnly = NULL, $ignoreTableAlias = NULL, $useColumnAliasesOnly = NULL) {
		if(!$this->columns){
			return false;
		}
		$filteredColumns = $this->filterIncludeExclude($this->columns, $thisTableAliasOnly, $ignoreTableAlias);

		if($SQL){
			if($useColumnAliasesOnly){
				foreach($filteredColumns as $column){
					$columns[] = "`{$column['tableAlias']}`.`{$column['columnAlias']}`";
				}
				return implode(",\r\n", $columns);
			} else {
				return implode(",\r\n", array_column($filteredColumns, "columnWithAlias"));
			}

		}

		return $filteredColumns;
	}

	private $table;
	private $tablesInUse = [];

	/**
	 * Takes a valid mySQL table name and return an alias.
	 * The alias is the table name + a suffix if it's the 2-n'th
	 * instance of the table.
	 *
	 * table_name,
	 * table_name2,
	 * table_name3,
	 * etc.
	 *
	 * @param $table
	 *
	 * @return string A alphanumeric string, not enclosed in SQL quotation marks
	 */
	private function getTableAlias($table){
		if(!$this->tablesInUse[$table]){
			$this->tablesInUse[$table] = 0;
		}

		# Clean up table name for alias purposes
		# The assumption here is that the table name contains at least one alphanumeric character.
		$table = preg_replace("/[^A-Za-z0-9_]/", '', $table);

		# If the table has been used before, increase the count
		$this->tablesInUse[$table]++;

		if($this->tablesInUse[$table]==1){
			//If this is the first table, omit the number suffix
			return $table;
		}

		# If this is table 2, 3, n, add the number as a suffix
		return $table.$this->tablesInUse[$table];
	}

	/**
	 * @param      $t
	 * @param null $a
	 *
	 * @return array|bool
	 */
	private function formatTableName($t, $a = NULL){
		if(is_array($t)){
			/**
			 * If the table has already been given an alias,
			 * it is assumed that the format is alias => table,
			 * because while several tables can be used in SQl statement,
			 * each alias can only be used once.
			 *
			 * No check is performed to ensure that the alias isn't already in use.
			 */
			$table = reset($t);
			$given = key($t);
			$alias = key($t) ? key($t) : $this->getTableAlias($table);
		} else if(is_string($t)){
			$table = $t;
			$given = is_string($a);
			$alias = is_string($a) ? $a : $this->getTableAlias($table);
			//if a specific alias has been given, it will override any computed alias.
		} else {
			return false;
		}

		$this->tablePrefix[$alias] = $table;

		return [
			"name" => $table,
			"given" => $given,
			"alias" => $alias
		];
	}

	/**
	 * Defines the Join On Condition and stores it in a given array variable.
	 *
	 * @param string       $jAlias
	 * @param string       $jTable
	 * @param string       $jGiven
	 * @param string|array $o
	 * @param array        $store
	 * @param bool			$or
	 *
	 * @return bool
	 */
	private function joinOn($jAlias, $jTable, $jGiven, $o, &$store, $or = NULL){
		# If the user wants to join with _no_ condition
		if($o === false){
			return false;
		}

		if(is_string($o)){
			/**
			 * The On Condition could simply be the name of the column the joined table,
			 * and the main table have in common.
			 */
			if($this->isTableReference($o)){
				$val = $o;
			} else {
				$val = "`{$this->table['alias']}`.`{$o}`";
			}
			$store[] = [
				"col" => $o,
				"val" => $val,
				"tableName" => $jTable,
				"tableAlias" => $jAlias,
				"not" => FALSE,
				"or" => $or
			];
			$this->addPrefix($jAlias, $jTable, $jGiven);
			return true;
		}

		if(is_array($o)){
			/**
			 * The most common way to represent the On Condition is though an array,
			 * especially if there are more than one condition
			 */
			foreach($o as $col => $val){
				$store[] = $this->onHandler($jTable, $jAlias, $col, $val, $or);
				$this->addPrefix($jAlias, $jTable, $jGiven, $val);
			}
			return true;
		}

		/**
		 * However, if no condition has been supplied, the assumption is that
		 * the joined table shares the ID column from the main table,
		 * and a join is created on that column.
		 */
		$store[] = [
			"col" => "{$this->table['name']}_id",
			"val" => "`{$this->table['alias']}`.`{$this->table['name']}_id`",
			"tableName" => $jTable,
			"tableAlias" => $jAlias,
			"not" => FALSE,
			"or" => $or
		];
		$this->addPrefix($jAlias, $jTable, $jGiven);
		return true;
	}

	private $tablePrefix = [];

	/**
	 * Creates and stores the table prefix, used to identify chained tables.
	 * The prefix is not the same as the alias, the prefix is only temporary,
	 * and is only to be used to create array trees from a flat array of results.
	 *
	 * @param string $jAlias
	 * @param string $jTable
	 * @param string $jGiven
	 * @param string $val
	 *
	 * @return bool
	 */
	private function addPrefix($jAlias, $jTable, $jGiven, $val = NULL){
		if(is_array($val)){
			//if the value is an array, meaning it belongs to an in() comparison
			foreach($val as $v){
				$this->addPrefix($jAlias, $jTable, $jGiven, $v);
			}
			return true;
		}

		# Check to see if the table is chained
		if(!$this->isTableReference($val)){
			if($jGiven){
				$this->tablePrefix[$jAlias] = $jGiven;
			} else {
				$this->tablePrefix[$jAlias] = $jTable;
				/**
				 * if the table is not chained, and a given name has been supplied,
				 * use it. Otherwise, use the table name.
				 */
			}
			return true;
		}

		# Extract the table chain if the alias is chained
		$valArray = explode(".", str_replace("`", "", $val));
		array_pop($valArray);

		# First degree children
//		foreach($valArray as $alias){
//			if($this->tablePrefix[$alias]){
//				if(substr_count($this->tablePrefix[$alias],".")){
//					$this->tablePrefix[$jAlias] = $this->tablePrefix[$alias].".";
//				} else {
//					$this->tablePrefix[$jAlias] = $this->tablePrefix[$alias].".";
//				}
//			}
//		}
		// The table prefix alias ($this->tablePrefix[$alias]) wasn't populated before

		# Second to nth degree children
		if($this->tablePrefix[$jAlias] != $jTable){
			//Use the alias or given name
			if($jGiven){
				$this->tablePrefix[$jAlias] .= $jGiven;
			} else {
				$this->tablePrefix[$jAlias] .= $jTable;
				/**
				 * In chained aliases, the table's actual name is used,
				 * not the alias, if a given name has not been supplied.
				 * As the alias is automatically generated, it can
				 * potentially be a variable that changes, thus cannot
				 * be used as a column table prefix.
				 */
			}
		}
		return true;
	}

	/**
	 * Used on joins to format the on-conditions.
	 *
	 * @param string $jTable
	 * @param string $jAlias
	 * @param string $col
	 * @param mixed $val
	 * @param null $or
	 *
	 * @return array
	 */
	private function onHandler($jTable, $jAlias, $col, $val, $or = NULL){
		if(is_int($col)) {
			//if the col is numerical, meaning, not a used col

			# If the val is a whole comparison statement
			if (str_replace(["=", "<", ">"], "", $val) != $val) {
				return [
					"col" => NULL,
					"val" => "({$val})",
					"tableName" => $jTable,
					"tableAlias" => $jAlias,
					"not" => FALSE,
					"or" => $or
				];
				/**
				 * The val is enclosed in parenthesis because it could contain
				 * two comparisons joined by an an OR, which would mess things
				 * up if the whole string wasn't enclosed.
				 *
				 * Example: "$col > $val OR $col IS NULL"
				 */
			}

			return [
				"col" => $val,
				"val" => "`{$this->table['alias']}`.`{$val}`",
				"tableName" => $jTable,
				"tableAlias" => $jAlias,
				"not" => FALSE,
				"or" => $or
			];
			/**
			 * Otherwise it's assumed the val is the name
			 * of the column to join back to the main table,
			 * and the same value is used on both sides of the
			 * comparison.
			 */
		}

		return [
			"col" => $col,
			"val" => $val,
			"tableName" => $jTable,
			"tableAlias" => $jAlias,
			"not" => FALSE,
			"or" => $or
		];
	}

	/**
	 * WHERE and OR clause handler
	 *
	 * Handles the WHERE COL = VAL AND/OR COL = VAL part of the SQl query.
	 *
	 * @param $col
	 * @param $val
	 * @param $table
	 * @param $alias
	 * @param $not
	 *
	 * @return mixed|string
	 */
	private function clauseHandler($col, $val, $table, $alias, $not){
		if(is_int($col) || !$col) {
			//If the column key is a numerical index only, and is not be used in the Where Clause
			//assume that the entire val string is a comparison, and that the user knows what they're doing
			return "({$val})";
			/**
			 * The val is enclosed in parenthesis because it could contain
			 * two comparisons joined by an an OR, which would mess things
			 * up if the whole string wasn't enclosed.
			 */
		} else if(!is_string($col)) {
			//If the column key is not numerical, NOR string
			return false;
		}

		# Column formatting
		//Ensure the column has the correct table prefix
		if(!$this->isTableReference($col)){
			//if the column isn't already formatted/prefixed

			# Check to see if the column exists in the table
			if(is_array($this->getTableMetadata($table, true))
			&& !in_array($col, array_column($this->getTableMetadata($table, true),"COLUMN_NAME"))){
				//if the check can be done, and
				//if the column doesn't exist
				return false;
			}

			$col = "`{$alias}`.`$col`";
			//the allows for any table name and any column name to be represented
			//even those with names containing reserved words
		}

		if(!list($eq, $val) = $this->whereValHandler($val, $not)){
			return false;
		}

		if($not && !in_array($val, ["NULL", "NOT NULL"])){
			return "({$col} {$eq} {$val} OR {$col} IS NULL)";
			/**
			 * Experimental. In cases where a value is NOT something,
			 * we have to include IS NULL or else the values
			 * that are NULL will also be excluded.
			 * In cases where the value is NULL or NOT NULL itself,
			 * ignore the suffix.
			 */
		}

		return "{$col} {$eq} {$val}";
	}

	/**
	 * Checks to see if a string follows one of the following patterns:
	 *
	 * <code>
	 * NULL
	 * </code>
	 * <code>
	 * NOW()
	 * </code>
	 * <code>
	 * 'val'
	 * </code>
	 * <code>
	 * `table`.column
	 * </code>
	 * <code>
	 * `table`.`column`
	 * </code>
	 *
	 * @param string $string The string to examine.
	 *
	 * @return bool TRUE if string fits the pattern, FALSE otherwise
	 */
	private function isExemptFromQuotationMark($string){
		# Check to see if the value is a NULL or NOT NULL value
		if(in_array($string, ["NULL", "NOT NULL", "NOW()"])){
			return true;
		}

		# Check to see if the val is already enclosed with quotation marks
		if (strlen($string) > 1 && $string[0] == "'" && $string[-1] == "'") {
			return true;
		}

		if($this->isTableReference($string)){
			return true;
		}

		return false;
	}

	/**
	 * Check to make sure that if a table reference,
	 * they follow the <code>`table`.col</code> or <code>`table`.`col`</code> pattern.
	 *
	 * @param $string
	 *
	 * @return bool TRUE on yes, this is a table reference, or FALSE otherwise
	 */
	private function isTableReference($string) {
		if(!is_string($string)){
			return false;
		}
		if(substr($string, 0,1) == "`"
		&& substr_count($string, ".") == 1
		&&(substr_count($string, "`") == 2 || substr_count($string, "`") == 4)
		){
			return true;
		}

		return false;
	}

	/**
	 * Handles formatting of the value and the function or format of the comparative element,
	 * for WHERE and ON values.
	 *
	 * @param mixed $val  Any value to to be compared.
	 * @param bool  $not  If set, the negated value is sought.
	 * @param bool  $html If set, will treat the string as HTML.
	 *
	 * @return array|bool
	 */
	private function whereValHandler($val, $not = NULL, $html = NULL){
		# "col" => ["val", "val"],
		if(is_array($val)){
			//if the value is an array, it will be treated as an IN(val[,val])
			foreach ($val as $v) {
				if($this->isExemptFromQuotationMark($v)) {
					//if the value in the array is a reference to another table
					$inArray[] = $v;
				} else {
					//if the value not a reference, and just a value
					if ($v = str::i($v)) {
						//if anything is left after cleansing the value
						$inArray[] = "'$v'";
						//add it to the array
					}
				}
			}

			if (!$inArray) {
				//if no values are accepted
				return true;
				//ignore this where clause
			}

			$eq = $not ? "NOT IN" : "IN";
			$val = implode(',', $inArray);
			$val = "({$val})";
			return [$eq, $val];
		}

		# "col" => NULL,
		if($val === NULL){
			$val = "NULL";
			$eq = $not ? "IS NOT" : "IS";
			return [$eq, $val];
		}

		# "col" => true,
		if($val === TRUE){
			$val = 1;
			$eq = $not ? "<>" : "=";
			return [$eq, $val];
		}

		# Tidy up the value
		$val = str::i($val, $html);

		# "col" => "%val%",
		if (strpos($val, "%") !== FALSE) {
			//if a "like" is (potentially) required
			$eq = $not ? "NOT LIKE" : "LIKE";
			$val = "'{$val}'";
			return [$eq, $val];
		}

		# "col" => "NOW()",
		if($val == "NOW()"){
			$eq = $not ? "!=" : "=";
			return [$eq, $val];
		}

		# "col" => "0", "col" => 0,
		if ($val === "0" || $val === 0){
			$eq = $not ? "<>" : "=";
			return [$eq, $val];
		}

		# "col" => `table`.column
		if($this->isExemptFromQuotationMark($val)) {
			//if the value is a reference to another table column
			//do not add quotation marks around the value
			$eq = $not ? "!=" : "=";
			return [$eq, $val];
		}

		if($val){
			$eq = $not ? "!=" : "=";
			$val = "'{$val}'";
			return [$eq, $val];
		}

		# If no val has been submitted
		return false;
		/**
		 * No val submitted does NOT equal "IS NULL or "= NULL"
		 * This way, empty vars can be submitted to a where clause,
		 * without there being an expectation that the key value is NULL.
		 * Instead the key will be ignored.
		 */
	}

	/**
	 * Handles formatting of the value and the function or format of the comparative element,
	 * for SET values, and warns of any improper values.
	 *
	 * @param           $col  string For reference use only if there is an error
	 * @param           $val  mixed The value itself
	 * @param bool|null $html bool If set to TRUE will allow HTML in the value
	 *
	 * @return bool|int|mixed|string
	 */
	private function setValHandler(string $col, $val, ?bool $html = NULL){
		# "col" => false,
		if($val === false){
			return false;
		}

		# "col" => ["val", "val"],
		if(is_array($val)){
			if(empty($val)) {
				//if it's an empty array
				return "NULL";
			} else {
				//if it's a non-empty array with a single empty key and empty sub value
				while(true){
					foreach($val as $k => $v){
						if(trim($k)||trim($v)){
							break 2;
						}
					}
					return "NULL";
				}
			}
			throw new \mysqli_sql_exception("Set values cannot be in array form. The following array was attempted set for the <b>{$col}</b> column: <pre>".print_r($val,true)."</pre>");
		}

		# "col" => "NOW()",
		if($val == "NOW()"){
			return $val;
		}

		# "col" => NULL,
		if($val === NULL){
			return "NULL";
		}

		# "col" => "0", "col" => 0,
		if ($val === "0" || $val === 0){
			return $val;
		}

		# Tidy up the value
		$val = str::i($val, $html);

		# "col" => `table`.`column`
		if($this->isExemptFromQuotationMark($val)) {
			//if the value is a reference to another table column
			//do not add quotation marks around the value
			return $val;
		}

		if($val){
			$val = "'{$val}'";
			return $val;
		}

		# If no val has been submitted
		$val = "NULL";
		return $val;
	}

	/**
	 * Adds an "AND", "OR" or "ON" WHERE condition.
	 *
	 * @param string       $type either "on" or "and" or "or" or the special "and_or", case insensitive
	 * @param string|array $w
	 * @param string       $table
	 * @param string       $alias
	 * @param bool         $not
	 *
	 * @return bool
	 */
	private function setCondition($type, $w, $table = NULL, $alias = NULL, $not = NULL) {
		$type = strtolower($type);

		if(is_string($w)){
			$condition[] = $w;
		} else if(is_array($w)){
			$condition = $w;
		} else {
			//if there is no where string or array submitted
			return true;
		}

		if($type == "or" && str::isNumericArray($condition)){
			do {
				foreach($condition as $col => $val){
					if(!is_numeric($col) || !is_array($val)){
						//we're only interested in OR arrays that *only* contain arrays
						break;
					}
				}
				foreach($condition as $condition_group){
					if(!$this->setCondition("and_or", $condition_group, $table, $alias, $not)){
						return false;
					}
				}
				return true;
			} while(0);
		}

		$tableName = $table ?: $this->table['name'];
		//if there is no table submitted, assume it's the main table

		$tableAlias = $alias ?: $this->table['alias'];
		//if there is no alias submitted, assume it's the main table alias

		if($type == "and_or"){
			$and_or_key = is_array($this->where['or']) ? count($this->where['or']) : 0;
			/**
			 * The and_or_key is used for the and_or type only.
			 * The and_or type is a collection of OR conditions, each one
			 * will be wrapped in an AND condition.
			 * To keep them separate, each collection gets an incremental key.
			 */
		}

		foreach($condition as $col => $val){
			if ($val === FALSE) {
				continue;
			}
			/**
			 * The only value we're ignoring here, is if the value is set to
			 * false. This can only be done by design.
			 *
			 * Values set to NULL or "" or 0 are all valid values,
			 * thus we cannot use the empty() method here.
			 */

			# Option for one column, multiple OR values
			if($type == 'or' && is_array($val) && str::isNumericArray($val)){
				/**
				 * "or" => ["col" => ["NULL", "val"]
				 *
				 * will effectively be translated to:
				 *
				 * "or" => [
				 * 	"col" => "NULL",
				 * 	"col" => "val"
				 * ]
				 */
				foreach($val as $v){
					$this->{$type}[] = [
						"col" => $col,
						"val" => $v,
						"tableName" => $tableName,
						"tableAlias" => $tableAlias,
						"not" => $not,
					];
				}

				continue;
			}

			# Do the same thing as above, but this time for the AND_OR hybrids
			if($type == 'and_or' && is_array($val) && str::isNumericArray($val)) {
				foreach($val as $v){
					$this->where['or'][$and_or_key][] = [
						"col" => $col,
						"val" => $v,
						"tableName" => $tableName,
						"tableAlias" => $tableAlias,
						"not" => $not,
					];
				}
				continue;
			}

			$condition_array = [
				"col" => $col,
				"val" => $val,
				"tableName" => $tableName,
				"tableAlias" => $tableAlias,
				"not" => $not,
			];

			if($type == "join"){
				$this->join[$tableAlias]["on"][] = $condition_array;
			} else if($type == "and_or"){
				$this->where['or'][$and_or_key][] = $condition_array;
			} else {
				$this->{$type}[] = $condition_array;
			}
		}

		return true;
	}

	/**
	 * String containing DISTINCT value.
	 * @var
	 */
	private $distinct;

	/**
	 * If distinct is NOT set, it will erase the distinct vale.
	 *
	 * @param $distinct
	 *
	 * @return bool
	 */
	private function setDistinct($distinct){
		if($distinct){
			$this->distinct =  "DISTINCT";
		}
		return true;
	}

	/**
	 * @return mixed
	 */
	private function getDistinct(){
		return $this->distinct;
	}

	/**
	 * If a row count of either the entire row or a particular column is required.
	 *
	 * @param string|bool $count If set to TRUE will return a general row count.
	 *                           If set to a string, will return the count of
	 *                           the corresponding column name.
	 * @param string $table      if there is no table submitted, it is assumed
	 *                           that you're working with main table.
	 * @param string      $alias if there is no alias submitted, it is assumed
	 *                           that you're working with main alias.
	 *
	 * @return bool Returns FALSE if no count is set, otherwise true
	 */
	private function setCount($count = NULL, $table = NULL, $alias = NULL){
		$tableName = $table ?: $this->table['name'];
		//if there is no table submitted, assume it's the main table

		$tableAlias = $alias ?: $this->table['alias'];
		//if there is no alias submitted, assume it's the main table alias

		# If a particular column is to be counted
		if(is_string($count)){
			//if the count variable is actually a column name
			$column = $count;

			$columnName = "COUNT(`{$tableName}`.`{$column}`)";
			$columnAlias = "{$column}_count";

			# Add new count column
			$this->columns[] = [
				"tableName" => $tableName,
				"tableAlias" => $tableAlias,
				"columnName" => $columnName,
				"columnAlias" => $columnAlias,
				"columnWithAlias" => "{$columnName} AS '{$columnAlias}'"
			];
			$this->needsGroupBy = true;

			# Remove existing non-count column if it exists (otherwise the count won't work)
			if(($key = array_search($column, array_column($this->columns, "columnName"))) !== FALSE){
				unset($this->columns[$key]);
			}

			return true;
		}

		# If a general row count is requested
		if($count){
			# Remove existing columns
			$this->columns = [];

			# Remove order by array
			$this->orderBy = [];

			# Add the count column
			$this->columns[] = [
				"tableName" => $tableName,
				"tableAlias" => $tableAlias,
				"columnName" => "COUNT(*)",
				"columnAlias" => "count",
				"columnWithAlias" => "COUNT(*) AS 'count'"
			];

			return true;
		}

		return false;
	}

	/**
	 * Used to reset all class variables before the next query is run.
	 *
	 * This is necessary as the sql class is only called once per (AJAX) request,
	 * thus the class variables need to be purged before each query run.
	 *
	 * @return bool
	 */
	private function reset(){
		$this->columns = NULL;
		$this->join = NULL;
		$this->or = NULL;
		$this->groupBy = NULL;
		$this->needsGroupBy = NULL;
		$this->orderBy = NULL;
		$this->where = NULL;
		$this->limits = NULL;
		$this->table = NULL;
		$this->tablePrefix = NULL;
		$this->tablesInUse = NULL;
		$this->distinct = NULL;
		$this->flat = NULL;
		return true;
	}

	private $flat;

	/**
	 * Checks to see if this query is to be returned flat or not.
	 *
	 * @param null $flat
	 *
	 * @return bool
	 */
	private function isFlat($flat = NULL){
		if($flat === NULL){
			return $this->flat;
		}
		if($flat){
			$this->flat = true;
		} else {
			$this->flat = false;
		}
		return true;
	}

	/**
	 * SQL select statement. Accepts the following:
	 *
	 * @param array|string $a <code>"count" => TRUE,</code>
	 *                 Either a boolean TRUE or a column name from the main table.
	 *                 If a column name is selected, a column with an alias as <i>column_count</i>
	 *                 is created, containing the count when all other columns are grouped.
	 *
	 *                 <code>"columns" => [],</code>
	 *                 Either a boolean FALSE or a string or array of column names.
	 *                 It's helpful to define the columns if you're summing or counting.
	 *
	 *                 <code>"or" => ["column" => "value"]<br/>"or" => ["column" => [1, 2]]<br/>"or" => [["column" => [1,2]],["other_column" => [1,2]]</code>
	 *                 OR clauses will be joined together in a single AND clause.
	 *                 If an OR column value is an array, it will be treated as
	 *                 multiple OR clauses for the same column.
	 *                 Alternatively, you can join several OR collections together, each one will be wrapped in an AND
	 *
	 *                 <code>"start" => 0,"length" => 0, OR "limit" => 0,</code>
	 *                 Starts is the row to start from, length is the number of rows from start.
	 *                 Limit, is the number of rows from row 1.
	 *
	 * @param bool  $return_query If set to TRUE, will return the final SQL query instead of running it.
	 *
	 * @return bool|mixed
	 */
	public function select($a, $return_query = FALSE){
		if(is_string($a)){
			//If the select method variable is just a SQL string
			if($results = $this->run($a)){
				return $results['rows'];
			}
		} else if(!is_array($a)){
			throw new \mysqli_sql_exception("SQL select calls must be either in string or array format.");
		} else {
			extract($a);
		}

		# Reconnect (for long running scripts)
		if($reconnect){
			$this->reconnect();
		}

		# Reset all the class variables, as they will have lingered from the last SQL query
		$this->reset();

		# If "flat" => true, the query results will not be normalised
		$this->isFlat($flat);

		# Distinct
		$this->setDistinct($distinct);

		# Ensure the main table exists
		if(!$this->getTableMetadata($table, true)){
			//By default, you can select (search for) _all_ columns
			return false;
		}

		# The main table
		$this->table = $this->formatTableName($table, $alias);
		//list($this->table['name'], $this->table['alias']) = $this->formatTableName($table, $alias);

		# Include removed?
		$this->includeRemoved($this->table['name'], $this->table['alias'], $include_removed, $this->where);

		# Add main table columns
		if(!$this->setColumns($this->table['name'], $this->table['alias'], $columns)){
			return false;
		}

		# Add where (and where NOT) condition
		$this->setCondition("where", $where);
		$this->setCondition("where", $where_not, NULL, NULL, true);

		# If a specific row (by ID) has been asked for
		if($id){
			$this->setCondition("where", ["{$this->table['name']}_id" => $id], $this->table['name'], $this->table['alias']);
		}

		# Add "or" (and "or NOT") condition
		$this->setCondition("or", $or);
		$this->setCondition("or", $or_not, NULL, NULL, true);

		# Incorporate the OR clauses to there WHERE clause
//		$this->mergeOrWithAnd();

		# Add joins
		if(!$this->addJoin("INNER", $join)){
			return false;
		}
		if(!$this->addJoin("LEFT", $left_join)){
			return false;
		}

		# Count (if only a row count is requested)
		$this->setCount($count);

		# Group by
		$this->setGroupBy($group_by);

		# Order by
		$this->setOrderBy($order_by);

		# Limits (start, length, limit)
		$this->setLimits($start, $length, $limit);

		# Ensure limits don't cut joined rows that belong together
		if($this->limits && $this->join){
			//if there are limits AND joins
			$mainTable[] = "(";
			$mainTable[] = "SELECT";
			$mainTable[] = $this->getDistinct();
			$mainTable[] = $this->getColumns(true, $this->table['alias']);
			$mainTable[] = "FROM `{$this->table['name']}` AS `{$this->table['alias']}`";
			$mainTable[] = $this->getConditions("WHERE", $this->where, $this->or, $this->table['alias']);
			$mainTable[] = $this->getGroupBy($this->table['alias']);
			$mainTable[] = $this->getOrderBy($this->table['alias']);
			$mainTable[] = $this->getLimits();
			$mainTable[] = ") AS `{$this->table['alias']}`";

			# Remove limits (so it's not used again below)
			$this->unsetLimits();

			# Get all the columns, but only their aliases
			$formattedColumnArray[] = $this->getColumns(true, $this->table['alias']);
			$formattedColumnArray[] = $this->getColumns(true, false, $this->table['alias'], false);
			$formattedColumns = implode(",\r\n",array_filter($formattedColumnArray));

			# Remove the main table where conditions (as they've already been accounted for above)
			$whereConditions = $this->getConditions("WHERE", $this->where, $this->or, NULL, $this->table['alias']);

			# Remove the main table group-by columns (as they've already been accounted for above)
			$groupByConditions = $this->getGroupBy(NULL, $this->table['alias']);
		} else {
			//if there are limits OR joins, or none (most common)

			# Get all the columns, with their names AND aliases
			$formattedColumns = $this->getColumns(true);

			# Keep the main table as is
			$mainTable[] = "`{$this->table['name']}` AS `{$this->table['alias']}`";

			# Include all the where conditions, including the ones for the main table
			$whereConditions = $this->getConditions("WHERE", $this->where, $this->or);

			# Include all the group-by columns, including the ones for the main table
			$groupByConditions = $this->getGroupBy();
		}

		$query[] = "SELECT";
		$query[] = $this->getDistinct();
		$query[] = $formattedColumns;
		$query[] = "FROM ".implode(array_filter($mainTable), " ");
		$query[] = $this->getJoins();
		$query[] = $whereConditions;
		$query[] = $groupByConditions;
		$query[] = $this->getOrderBy();
		$query[] = $this->getLimits();

		$SQL = implode(array_filter($query), "\r\n");

		if($return_query){
			//If the query is to be returned, NOT run
			return $SQL;
		}

		if (!($result = $this->run($SQL))) {
			return false;
		}

		if ($result['rowcount'] == 0) {
			//if no rows are found, return false
			return false;
		}

		if ($count === TRUE){
			//if (only) the row count is requested
			return $result['rows'][0]['count'];
		}

		if ($id || $limit == 1) {
			/**
			 * If a specific main table ID has been given,
			 * or if the limit has been set to 1, only return
			 * a single row.
			 */
			return $result['rows'][0];
		}

		# Otherwise, and most commonly, return all the result rows
		return $result['rows'];
	}

	/**
	 * Filters items for table aliases.
	 *
	 * @param      $items
	 * @param null $thisTableAliasOnly
	 * @param null $ignoreTableAlias
	 *
	 * @return array
	 */
	private function filterIncludeExclude($items, $thisTableAliasOnly = NULL, $ignoreTableAlias = NULL){
		return array_filter($items, function ($item) use ($thisTableAliasOnly, $ignoreTableAlias) {
			# Only include
			if(is_array($thisTableAliasOnly)){
				//if there are many tables to include
				return (in_array($item['tableAlias'],$thisTableAliasOnly));
			} else if($thisTableAliasOnly){
				//if there is only one table to include
				return ($item['tableAlias'] == $thisTableAliasOnly);
			}

			# Only exclude
			if(is_array($ignoreTableAlias)) {
				//if there are many tables to exclude
				return (!in_array($item['tableAlias'],$ignoreTableAlias));
			} else if($ignoreTableAlias){
				//if there is only one table to exclude
				return ($item['tableAlias'] != $ignoreTableAlias);
			}
			return true;
		});
	}

	/**
	 * Generate WHERE or ON strings based on arrays of AND and OR conditions.
	 *
	 * @param string $prefix             "WHERE" or "ON", depending on use
	 * @param array  $andConditions      All the AND conditions.
	 * @param array  $orConditions       All the OR conditions.
	 * @param string $thisTableAliasOnly Table name
	 * @param string $ignoreTableAlias
	 * @param bool   $returnAndStringsArray If set to TRUE, will *only* return an array of and strings, not a string of SQL
	 *
	 * @return bool|string|array
	 */
	private function getConditions($prefix, $andConditions = NULL, $orConditions = NULL, $thisTableAliasOnly = NULL, $ignoreTableAlias = NULL, $returnAndStringsArray = NULL) {
		if(!empty($andConditions)){
			# Handle the AND_OR hybrid condition
			if($andConditions['or']){
				$and_strings = [];
				foreach($andConditions['or'] as $and_or_key => $conditions){
					$and_strings = array_merge($and_strings, $this->getConditions($prefix, NULL, $conditions, $thisTableAliasOnly, $ignoreTableAlias, TRUE));
				}
				unset($andConditions['or']);
			}
			$filteredAndConditions = $this->filterIncludeExclude($andConditions, $thisTableAliasOnly, $ignoreTableAlias);
		}

		if(!empty($orConditions)){
			$filteredOrConditions = $this->filterIncludeExclude($orConditions, $thisTableAliasOnly, $ignoreTableAlias);
		}

		if(!empty($filteredOrConditions) && count(array_filter($filteredOrConditions)) == 1){
			//if there is only one OR, it's not really an OR
			$filteredAndConditions[] = reset(array_filter($filteredOrConditions));
			/**
			 * OR comparisons are only compared to each other, not to the AND comparisons,
			 * thus if there is only 1 OR comparisons, it has nothing to be compared to,
			 * and can be included directly with the AND comparisons.
			 */
		} else if(!empty($filteredOrConditions)){
			//if there are multiple OR statements
			foreach($filteredOrConditions as $clause){
				$or_strings[] = $this->clauseHandler($clause['col'], $clause['val'], $clause['tableName'], $clause['tableAlias'], $clause['not']);
			}
			if(!empty(array_filter($or_strings))){
				$and_strings[] = "(\r\n\t" . implode("\r\n\tOR ", array_filter($or_strings)) . "\r\n)";
			}
		}

		if(!empty($filteredAndConditions)) {
			foreach ($filteredAndConditions as $clause) {
				$and_strings[] = $this->clauseHandler($clause['col'], $clause['val'], $clause['tableName'], $clause['tableAlias'], $clause['not']);
			}
		}

		# If only the array is to be returned
		if($returnAndStringsArray) {
			return $and_strings ?: [];
		}

		if(is_array($and_strings) && array_filter($and_strings)){
			return "{$prefix} ".implode("\r\nAND ", array_filter($and_strings));
		}

		return false;
	}

	/**
	 * Merges the OR comparisons with the AND comparisons.
	 *
	 * Note:
	 * OR comparisons are only compared to each other, not to the AND comparisons.
	 *
	 * @return bool
	 */
//	private function mergeOrWithAnd(){
//		if(empty($this->or)){
//			return false;
//		}
//
//		if(count(array_filter($this->or)) == 1){
//			//if there is only one OR, it's not really an OR
//			$this->where[] = reset(array_filter($this->or));
//			/**
//			 * OR comparisons are only compared to each other, not to the AND comparisons,
//			 * thus if there is only 1 OR comparisons, it has nothing to be compared to,
//			 * and can be included directly with the AND comparisons.
//			 */
//
//		} else {
//			//if there are multiple OR statements
//			$this->where[] = "(\r\n\t" . implode("\r\n\tOR ", array_filter($this->or)) . "\r\n)";
//		}
//
//
//		return true;
//	}

	/**
	 * Make sure that the *USER* does not try to insert values
	 * into fields they're not allowed to insert values into.
	 *
	 * @param string $table The relevant table
	 * @param array  $data  Key/val pairs of column/content to be inserted/updated in the table.
	 *
	 * @return bool
	 */
	private function removeIllegalColumns(&$data, $table){
		if(empty($data)){
			return true;
		}

		if(!$tableMetadata = $this->getTableMetadata($table, false)){
			return false;
		}

		foreach($data as $col => $val) {
			if(!in_array($col, array_column($tableMetadata, "COLUMN_NAME"))){
				//If the column doesn't exist (as one of the column the user can update) in the table
				unset($data[$col]);
			}
		}

		return true;
	}

	/**
	 * Generate a UUID
	 *
	 * UUID stands for Universally Unique IDentifier and is defined in the `RFC 4122`.
	 * It is a 128 bits number, normally written in hexadecimal and split by dashes
	 * into five groups. A typical UUID value looks like:
	 * <code>
	 * 905b194e-b7ab-42c2-af21-7dbd33e227e3
	 * </code>
	 * This method will return a "COMB", a timestap first random UUID.
	 * So-called because they COMBine random bytes with a timestamp, the
	 * timestamp-first COMB codec replaces the first 48 bits of a version 4,
	 * random UUID with a Unix timestamp and microseconds, creating an
	 * identifier that can be sorted by creation time. These UUIDs are
	 * monotonically increasing, each one coming after the previously-created
	 * one, in a proper sort order.
	 *
	 * @link https://github.com/ramsey/uuid
	 * @link https://uuid.ramsey.dev/en/latest/customize/timestamp-first-comb-codec.html
	 * @link https://www.percona.com/blog/2019/11/22/uuids-are-popular-but-bad-for-performance-lets-discuss/
	 *
	 * @return string Returns char(36) with a timestamp-first COMB codec UUID
	 */
	public function generateUUID(){
		$factory = new UuidFactory();
		$codec = new TimestampFirstCombCodec($factory->getUuidBuilder());

		$factory->setCodec($codec);

		$factory->setRandomGenerator(new CombGenerator(
			$factory->getRandomGenerator(),
			$factory->getNumberConverter()
		));

		$timestampFirstComb = $factory->uuid4();

		return $timestampFirstComb->toString();
	}

	/**
	 * SQL insert function, returns the insert_id
	 *
	 * <pre>
	 * if(!$insert_id = $this->sql->insert([
	 * 	"table" => 'table_name',
	 * 	"set" => $_REQUEST
	 * ])){
	 * 	return false;
	 * }
	 * </pre>
	 *
	 * @param array $a An array with SQL insert data.
	 * @param bool $return_query If set to true, will only return the SQL query, not execute it.
	 *
	 * @return bool|int	Returns bool FALSE on error, or an integer value if a row has been inserted.
	 */
	public function insert(array $a, bool $return_query = NULL){
		if(is_array($a)){
			extract($a);
		} else {
			throw new \mysqli_sql_exception("SQL insert calls must be in an array format.");
		}

		# Reconnect (for long running scripts)
		if($reconnect){
			$this->reconnect();
		}

		# Grow the columns of the if they don't exist / are too small for the data
		if($grow){
			if(!$this->growTable($table, $set)){
				return false;
			}
		}

		$this->removeIllegalColumns($set, $table);

		# Generate the table_id if one hasn't been generated for it
		$set["{$table}_id"] = $this->generateUUID();

		# Set the row created date value
		$set['created'] = "NOW()";

		# Set the created by (if set)
		global $user_id;
		$set['created_by'] = $user_id ?: "NULL";
		//at times, there may not be a given user performing the insert (user creation, etc)

		# Prepare the array to insert into the table
		$setArray = $this->generateSetArray($set, $table);

		# Generate the query
		$query[] = "INSERT INTO `{$table}` SET ";
		$query[] = implode(",\r\n", $setArray);

		$SQL = implode( "\r\n", $query);

		# If set to true, return the query instead of running it
		if($return_query){
			return $SQL;
		}

		if (!($result = $this->run($SQL))) {
			return false;
		}

		# Store the *insert* query
		$insertQuery = $_SESSION['query'];

		# Insert values in the audit trail
		if($audit_trail == true){
			$this->columns = NULL;
			/**
			 * Audit trails need to be invoked
			 */
			if(!$this->addToAuditTrail($table, $set["{$table}_id"], $set, TRUE)){
				return false;
			}
		}

		# Restore the insert query (we're not interested in the audit trail query)
		$_SESSION['query'] = $insertQuery;

		# Return the inserted ID _we made_ (cause we make the IDs)
		return $set["{$table}_id"];
	}

	/**
	 * Create and run a SQL update query.
	 *
	 * @param      $a
	 * @param null $return_query
	 *
	 * @return bool
	 * @throws \Exception
	 * @throws \Exception
	 */
	public function update($a, $return_query = NULL){
		if(!is_array($a)){
			throw new \Exception("SQL update calls must be in an array format.");
		}

		extract($a);

		# Make sure there is a set to update
		if (!is_array($set)) {
			throw new \Exception("An update request was sent with no columns to update.");
		}

		# Make sure the column or columns are identified
		if (!$id && !$where) {
			throw new \Exception("An update request was sent with no WHERE clause or column ID to identify what to update.");
		}

		# Reconnect (for long running scripts)
		if($reconnect){
			$this->reconnect();
		}

		# Grow the columns of the if they don't exist / are too small for the data
		if($grow){
			if(!$this->growTable($table, $set)){
				return false;
			}
		}

		# If the user_id has not been set (most common)
		if(!array_key_exists("user_id", $a)){
			global $user_id;
			if(!$user_id){
				throw new \Exception("Updating without a user ID is not allowed.");
			}
		}

		$this->removeIllegalColumns($a['set'], $table);

		$a['set']['updated'] = "NOW()";
		$a['set']['updated_by'] = $user_id;

		return $this->runUpdate($a, $return_query);
	}

	/**
	 * Runs the last mile of updating/removing/restoring a record.
	 *
	 * @param array $a
	 *
	 * @param null  $return_query
	 *
	 * @return bool
	 */
	private function runUpdate($a, $return_query = NULL){
		extract($a);

		# Reset variables
		$this->reset();

		# Ensure the main table exists
		if(!$this->tableExists($table)){
			throw new \mysqli_sql_exception("The <code>{$table}</code> table does not exist.");
		}

		# The main table (the $this->table array is used by the joins)
		$this->table = $this->formatTableName($table, $alias);

		# The joins
		$this->setUpdateJoin("INNER", $join);
		$this->setUpdateJoin("LEFT", $left_join);

		# The main table (loaded again by design, as the $this->table array has been reset by the joins)
		if($this->join){
			$this->table = $this->formatTableName($table, $alias);
		}

		# Include removed?
		$this->includeRemoved($this->table['name'], $this->table['alias'], $include_removed, $this->where);

		# Add where (and where NOT) condition
		$this->setCondition("where", $where);
		$this->setCondition("where", $where_not, NULL, NULL, true);

		# If a specific row (by ID) has been asked for
		if($id){
			$this->setCondition("where", ["{$this->table['name']}_id" => $id], $this->table['name'], $this->table['alias']);
			$this->setLimits(false, false, 1);
		} else {
			$this->setLimits(false, false, $limit);
		}

		# Add "or" (and "or NOT") condition
		$this->setCondition("or", $or);
		$this->setCondition("or", $or_not, NULL, NULL, true);

		$setArray = $this->generateSetArray($set, $table);

		$query = [];

		$query[] = "UPDATE `{$this->table['name']}` AS `{$this->table['alias']}`";
		$query[] = $this->getUpdateJoins();
		$query[] = "SET";
		$query[] = implode(",\r\n", $setArray);
		$query[] = $this->getConditions("WHERE", $this->where, $this->or, $this->table['alias']);
		$query[] = $this->getLimits();

		$SQL = implode(array_filter($query), "\r\n");

		if($return_query){
			//If the query is to be returned, NOT run
			return $SQL;
		}

		if($id && $audit_trail == true) {
			/**
			 * Audit trails only work when ONE record (row) is being updated
			 * Audit trails must be invoked
			 *
			 * The audit trail is run first. Two considerations:
			 * 1. The last SQL query (var_dump(query)) will still be the update SQL query
			 * 2. If the update fails, the audit alert entry will be removed.
			 */
			if(($audit_trail_ids = $this->addToAuditTrail($table, $id, $set)) === false){
				return false;
			}
		}

		if (!$result = $this->run($SQL)){
			//If the update didn't work, remove the audit trail (if it was run)
			if(!empty($audit_trail_ids)){
				foreach($audit_trail_ids as $audit_trail_id){
					if(!$this->remove(["table" => "audit_trail", "id" => $audit_trail_id])){
						return false;
					}
				}
			}
			return false;
		}

		return $result;
	}

	/**
	 * Adds an UPDATE or an INSERT to the audit trail table.
	 *
	 * @param string $table  The name of the table values are being inserted into.
	 * @param int    $id     The value of the table ID. Only applies to UPDATE statements.
	 * @param array  $set    The values being updated or inserted.
	 * @param bool   $insert If an INSERT is being logged, set this value to TRUE.
	 *
	 * @return array|bool
	 */
	private function addToAuditTrail($table, $id, $set, $insert = NULL){
		if($table == "audit_trail"){return true;}
		//Prevents infinite loops

		if($table == "error_log"){return true;}
		//No need to alert errors in the audit trail

		if(!$insert){
			//if this is NOT an insert query
			if(!$old = $this->select(["table" => $table, "id" => $id])){
				//if the old cannot be found, it cannot be compared to the new
				return false;
			}
		}

		$insert_ids = [];
		foreach($set as $key => $val){
			if(!in_array($key, array_column($this->getTableMetadata($table, false),"COLUMN_NAME"))) {
				/**
				 * if this is NOT a column the user can update
				 * they they probably didn't and it's a system added column
				 * like updated and updated_by,
				 * so there is no need to compare it.
				 */
				if(!in_array($key, ["removed", "created"])){
					//Removed and created is an exception, so that we can track removals and creations
					continue;
				}
			}

			# For insert statements
			if($insert){
				# Log the change in the audit trail
				if(!$insert_ids[] = $this->insert([
					"table" => "audit_trail",
					"set" => [
						"subscription_id" => $set['subscription_id'],
						"rel_table" => $table,
						"rel_id" => $id,
						"column" => $key,
						"old_value" => NULL,
						"new_value" => $val
					]
				])){
					return false;
				}
				continue;
			}

			# For update (also restore, remove) statements
			if(key_exists($key, $old)){
				if($old[$key] != $val){
					/**
					 * If this is either
					 * A. an insert query, or
					 * B. a change in an existing value
					 */

					if($this->isTableReference($val)){
						//if the row being inserted refers to a table reference
						continue;
						//because we cannot insert a table reference as a value
					}

					# Log the change in the audit trail
					if(!$insert_ids[] = $this->insert([
						"table" => "audit_trail",
						"set" => [
							"subscription_id" => $set['subscription_id'],
							"rel_table" => $table,
							"rel_id" => $id,
							"column" => $key,
							"old_value" => $old[$key],
							"new_value" => $val
						]
					])){
						return false;
					}
				}
			}
		}
		return $insert_ids ?: TRUE;
		//Prevents the method to return an empty result,
		//even if things went well.
	}

	/**
	 * Runs the query that sets the removed field to NOW()
	 *
	 * <pre>
	if(!$this->sql->remove([
	"table" => $table,
	"id" => $id
	])){
	return false;
	}
	 * </pre>
	 * @global int $user_id
	 * @param array $a
	 * @return boolean
	 */
	public function remove($a) {
		if(!is_array($a)){
			throw new \mysqli_sql_exception("SQL remove calls must be in array format.");
		} else {
			extract($a);
		}

		if($user_id !== false){
			// The user ID requirement can be overridden by setting user_id = FALSE
			global $user_id;
			if(!$user_id){
				throw new \mysqli_sql_exception('Removing without a user_id is not allowed.');
			}
		}

		if(!$id && !$where && !$where_not){
			throw new \mysqli_sql_exception('Removing rows without an id or a where clause is too dangerous.');
		}

		# The user should not be sending any values with the restore request
		$a['set'] = [];

		$a['set']['removed'] = "NOW()";
		$a['set']['removed_by'] = $user_id;

		return $this->runUpdate($a);
	}

	/**
	 * mySQL DELETE function.
	 * Use with caution.
	 *
	 * <code>
	 * if(($this->sql->delete([
	 *    "table" => "inbox",
	 *    "where" => [
	 *        "folder_id" => $folder_id,
	 *        "subscription_id" => $subscription_id,
	 *        "seat_id" => $seat_id
	 *    ]
	 * ])) === FALSE){
	 *    return false;
	 * }
	 * </code>
	 *
	 * @param array $a Array of SQL delete data.
	 *
	 * @return bool|int Returns the number of rows deleted or FALSE on false
	 */
	public function delete($a){
		if(!is_array($a)){
			throw new \mysqli_sql_exception("SQL delete calls must be in array format.");
		} else {
			extract($a);
		}

		# Start with a clean slate
		$this->reset();

		# Ensure the main table exists
		if(!$this->getTableMetadata($table, true)){
			//By default, you can work with _all_ columns
			return false;
		}

		# The main table
		$this->table = $this->formatTableName($table, $alias);

		# Add where (and where NOT) condition
		$this->setCondition("where", $where);
		$this->setCondition("where", $where_not, NULL, NULL, true);

		# Add "or" (and "or NOT") condition
		$this->setCondition("or", $or);
		$this->setCondition("or", $or_not, NULL, NULL, true);

		if(!$where = $this->getConditions("WHERE", $this->where, $this->or)){
			throw new \mysqli_sql_exception("Deleting without any valid where clauses (essentially truncating the table) is not allowed. You were about to do that with the <code>{$this->table['name']}</code> table.");
		}

		$query[] = "DELETE FROM `{$this->table['name']}`";
		$query[] = $where;

		if (!($sql = $this->run(implode($query, "\r\n")))) {
			return false;
		}

		return $sql['rowcount'] ?: TRUE;
	}

	/**
	 * Restore a row
	 * <pre>
	 * if(!$this->sql->restore(array(
	 * "table" => $table,
	 * "id" => $id
	 * ))){
	 * return false;
	 * }
	 * </pre>
	 *
	 * @param array $a Array of SQL restore (update) data.
	 *
	 * @return bool
	 */
	public function restore($a){
		if(!is_array($a)){
			throw new \mysqli_sql_exception("SQL restore calls must be in array format.");
		} else {
			extract($a);
		}

		global $user_id;
		if(!$user_id){
			throw new \mysqli_sql_exception('Updating without a user_id is not allowed.');
		}

		if(!$id){
			throw new \mysqli_sql_exception('Restoring without an id is too dangerous.');
		}

		# The user should not be sending any values with the restore request
		$a['set'] = [];

		$a['set']['updated'] = "NOW()";
		$a['set']['updated_by'] = $user_id;
		$a['set']['removed'] = "NULL";
		$a['set']['removed_by'] = "NULL";

		return $this->runUpdate($a);
	}

	/**
	 * Generates an array of values to be SET
	 * as part of a SQL INSERT or UPDATE.
	 *
	 * Ensures the values are properly formatted.
	 * Does NOT check to see if the column being updated,
	 * is one that the user is allowed to update.
	 *
	 * @param array $data The key/values to be inserted into the table.
	 * @param string $table The relevant table.
	 *
	 * @return array|bool
	 */
	private function generateSetArray($data, $table){
		if(!$data){
			throw new \mysqli_sql_exception("No data was given to set in the <code>{$table}</code> table.");
		} else if(!is_array($data)){
			throw new \mysqli_sql_exception("The data given to set in the <code>{$table}</code> table is not in array format.");
		}

		if(!$table){
			throw new \mysqli_sql_exception("Table definition is missing");
		}

		if(!$tableMetadata = $this->getTableMetadata($table, true)){
			if(!$this->tableExists($table)){
				throw new \mysqli_sql_exception("The <code>{$table}</code> table does not exist.");
			}
			throw new \mysqli_sql_exception("Getting the metadata for the <code>{$table}</code> table failed.");
		}

		foreach($data as $col => $val){
			if(is_numeric($col)){
				//if col is a number, then val is the whole Set string
				$outputArray[] = $val;
				continue;
			}

			if(!$col){
				//If for some reason the column is not defined
				continue;
			}

			if($this->isTableReference($val)){
				//If the value is a table reference
				if(!preg_match("/`(.*)`\.(.*)/", $val, $matches)){
					//if it doesn't fit the pattern
					continue;
				}
				if(!$this->tableExists($matches[1])){
					//if the table doesn't exist
					continue;
				}

				if($this->columns
				&& !in_array($matches[2], array_column($this->columns, "columnName"))
				&& !in_array($matches[2], array_column($this->columns, "columnAlias"))){
					//if the column doesn't exist
					continue;
				}
			} else if($tableMetadata[$col]['CHARACTER_MAXIMUM_LENGTH']>255){
				/**
				 * Only columns that are of the TEXT/LONGTEXT type,
				 * or columns that are VARCHAR but have a max length LONGER
				 * than 255 are allowed to contain HTML.
				 * for all other column types, HTML tags are stripped.
				 */
				if(($val = $this->setValHandler($col, $val, true)) === FALSE){
					//A legit value can be "0"
					continue;
				}
			} else if($tableMetadata[$col]['DATA_TYPE'] == 'timestamp'){
				/**
				 * If the field is set to TIMESTAMP (and not DATETIME),
				 * the assumption is that the field is expecting UNIX timestamps.
				 * However, mySQL doesn't seem to accept actual timestamp INTs
				 * as input. Thus the input must be translated to a more
				 * traditional datetime string. This ignores timezones.
				 */
				if(is_numeric($val)){
					$val = date_create('@' . $val)->format("Y-m-d H:i:s");
				}
				if(($val = $this->setValHandler($col, $val)) === FALSE){
					//A legit value can be "0" or NULL
					continue;
				}
			} else if($tableMetadata[$col]['DATA_TYPE'] == 'tinyint'){
				/**
				 * The assumption here is that if the data type is tinyint,
				 * it's used as a de-facto boolean data type.
				 * Thus, boolean TRUE/FALSE values are translated to 1/NULL values.
				 */
				if($val === true){
					$val = 1;
				}
				if($val == "true"){
					$val = 1;
				}
				if($val === NULL){
					$val = "NULL";
				}
				if($val === false){
					$val = "NULL";
				}
				if($val == "false"){
					$val = "NULL";
				}
				$val = $this->setValHandler($col, $val);
			} else {
				if(($val = $this->setValHandler($col, $val)) === FALSE){
					//A legit value can be "0" or NULL
					continue;
				}
			}
			$outputArray[] = "`{$table}`.`{$col}` = {$val}";
		}

		return $outputArray;
	}

	/**
	 * Generates an array of values for either
	 * the WHERE clause or the ON clause in a SQL statement.
	 *
	 * @param array $data The key/values to be checked in a WHERE or ON clause.
	 * @param string $table The relevant table.
	 *
	 * @return array|bool
	 */
//	private function generateWhereArray($data, $table){
//		if(!is_array($data)){
//			return false;
//		}
//
//		if(!$table){
//			$this->alert->error("Table definition is missing");
//			return false;
//		}
//
//		if(!$tableMetadata = $this->getTableMetadata($table, true)){
//			return false;
//		}
//
//		foreach($data as $col => $val){
//			if(!$col){
//				//If for some reason the column is not defined
//				continue;
//			}
//			if(!in_array($col, array_column($tableMetadata, "COLUMN_NAME"))){
//				//If the column doesn't exist in the table
//				continue;
//			}
//			if($tableMetadata[$col]['DATA_TYPE'] == 'text'){
//				//Only columns that are of the TEXT type, can potentially contain HTML
//				//for all other column types, HTML tags are stripped
//				list($eq, $val) = $this->whereValHandler($val,false, true);
//			} else {
//				list($eq, $val) = $this->whereValHandler($val);
//			}
//			$outputArray[] = "`{$col}` {$eq} {$val}";
//		}
//
//		return $outputArray;
//	}

	/**
	 * @var string String that contains the `LIMIT n, n` clause.
	 */
	private $limits;

	/**
	 * Gets the row limit string, if set.
	 * Otherwise returns an empty string.
	 *
	 * @return mixed
	 */
	private function getLimits() {
		return $this->limits;
	}

	/**
	 * Clears the limits variable of any values.
	 */
	private function unsetLimits(){
		$this->limits = NULL;
	}

	/**
	 * Create the row limit string, based on either start+length or length/limit values.
	 * If neither of the three values are given, the string value is not set.
	 *
	 * @param $start
	 * @param $length
	 * @param $limit
	 *
	 * @return bool
	 */
	private function setLimits($start, $length, $limit) {
		if($start && $length){
			$this->limits = "LIMIT {$start}, {$length}";
			return true;
		}
		if($length){
			$this->limits = "LIMIT {$length}";
		}
		if($limit){
			$this->limits = "LIMIT {$limit}";
		}
		return true;
	}



	private $join;

	/**
	 * Adds a join to a SQL select (only) query.
	 *
	 * @param      $type
	 * @param null $j
	 *
	 * @return bool
	 */
	private function addJoin($type, $j = NULL){
		if(!$j){
			return true;
		}

		if(is_array($j) && !is_int(key($j))) {
			throw new \mysqli_sql_exception("Ensure the join array is double bracketed.");
		} else if(is_string($j)){
			$joins[] = ["table" => $j];
		} else {
			$joins = $j;
		}

		foreach($joins as $id => $join){

			# If the whole join is just the name of a table
			if(is_string($join)){
				$join = ["table" => $join];
			}

			if(!$join['table']){
				throw new \mysqli_sql_exception("A join was requested without a table name.");
			}

			$j = $this->formatTableName($join['table'], $join['alias']);

			$jOn = [];
			$jOn_or = [];

			# Define join on condition
			$this->joinOn($j['alias'], $j['name'], $j['given'], $join['on'], $jOn);
			$this->joinOn($j['alias'], $j['name'], $j['given'], $join['on_or'] ?: false, $jOn_or, true);
			// While the on condition may be missing at times (because it can be assumed), the on_or condition must exist

			# Add joined table columns
			if(!$this->setColumns($j['name'], $j['alias'], $join['columns'], true)){
				return false;
			}

			# Include removed?
			$this->includeRemoved($j['name'], $j['alias'], $join['include_removed'], $jOn);

			# Count
			$this->setCount($join['count'], $j['name'], $j['alias']);

			# Add where and or clauses for this joined table
			$this->setCondition("where", $join['where'], $j['name'], $j['alias']);
			$this->setCondition("where", $join['where_not'], $j['name'], $j['alias'], true);
			$this->setCondition("or", $join['or'], $j['name'], $j['alias']);
			$this->setCondition("or",$join['or_not'], $j['name'], $j['alias'], true);

			# Add optional order bys
			$this->setOrderBy($join['order_by'], $j['name'], $j['alias']);

			$this->join[$j['alias']] = [
				"type" => $type,
				"tableName" => $j['name'],
				"tableAlias" => $j['alias'],
				"on"=> $jOn,
				"on_or" => $jOn_or
			];
		}

		return true;
	}

	/**
	 * Adds a join to a SQL UPDATE (only) query.
	 * @param      $type
	 * @param null $j
	 *
	 * @return bool
	 */
	private function setUpdateJoin($type, $j = NULL){
		if(!$j){
			return false;
		}

		if(is_array($j) && !is_int(key($j))) {
			throw new \mysqli_sql_exception("Ensure the join array is double bracketed.");
		} else if(is_string($j)){
			$joins[] = ["table" => $j];
		} else {
			$joins = $j;
		}

		foreach($joins as $id => $join) {

			# If the whole join is just the name of a table
			if (is_string($join)) {
				$join = [];
				$join['table'] = $joins[$id];
			}

			if (!$join['table']) {
				throw new \mysqli_sql_exception("A join was requested without a table name.");
			}

			$j = $this->formatTableName($join['table'], $join['alias']);

			$jOn = [];
			$jOn_or = [];

			# Define join on condition
			$this->joinOn($j['alias'], $j['name'], $j['given'], $join['on'], $jOn);
			$this->joinOn($j['alias'], $j['name'], $j['given'], $join['on_or'], $jOn_or, true);

			$this->join[$j['alias']] = [
				"type" => $type,
				"tableName" => $j['name'],
				"tableAlias" => $j['alias'],
				"on"=> $jOn,
				"on_or"=> $jOn_or,
				"SQL" => $this->select($join, true)
			];
		}

		return true;
	}

	/**
	 * Returns update joins.
	 *
	 * @return bool|string
	 */
	private function getUpdateJoins(){
		if(!is_array($this->join)){
			//if this query has no joins
			return false;
		}

		foreach($this->join as $join){
			$sql[] = "{$join['type']} JOIN (";
			$sql[] = str_replace("\r\n", "\r\n\t", $join['SQL']);
			$sql[] = ") AS `{$join['tableAlias']}`";
			$sql[] = $this->getConditions("ON", $join['on'], $join['on_or']);
		}

		return implode($sql, "\r\n");
	}

	/**
	 * Get joins for SQL select queries.
	 *
	 * @return bool|string
	 */
	private function getJoins(){
		if(!is_array($this->join)){
			//if this query has no joins
			return false;
		}

		foreach($this->join as $join){
			$sql[] = "{$join['type']} JOIN `{$join['tableName']}` AS `{$join['tableAlias']}`";
			$sql[] = $this->getConditions("ON", $join['on'], $join['on_or']);
		}

		return implode($sql, "\r\n");
	}

	private $orderBy;

	/**
	 * Adds order by for the main table, or any joins.
	 * Order by for joined tables must be bundled with the
	 * join array.
	 *
	 * Columns that already include table references or that are functions are left alone.
	 *
	 * Columns that don't exist will be removed to prevent SQL errors.
	 *
	 * @param      $ob array Sanctions of columns as an array, "column" => "direction", to sort
	 * @param null $table string The table where the columns are being taken from
	 * @param null $alias string The table alias belonging to the columns
	 *
	 * @return bool
	 */
	private function setOrderBy($ob, $table = NULL, $alias = NULL) {
		if(is_string($ob)){
			$orderBy[$ob] = "ASC";
		} else if(is_array($ob)){
			$orderBy = $ob;
		} else {
			//if there is no where string or array submitted
			return true;
		}

		if(!$table){
			//if there is no table submitted, assume it's the main table
			$table = $this->table['name'];
		}

		if(!$alias){
			//if there is no alias submitted, assume it's the main table alias
			$alias = $this->table['alias'];
		}

		foreach ($orderBy as $column => $direction) {

			# Sanitize what could potentially be user input
			$column = str::i($column);
			$direction = str::i($direction);

			if($this->isFunction($column)){
				//if the order by column is a function
				$this->orderBy[][$column] = $direction;

			} else if($this->isTableReference($column)) {
				//if the order by is `alias`.column or `alias`.`column`
//				$this->orderBy[][$column] = $direction;
				/**
				 * The reason why this simplified version is not used is that
				 * in cases where there a joins and limits, we need to separate
				 * out the order bys based on table/alias. Thus the more
				 * metadata (like what table/alias it belongs to) we have
				 * about a order by, the better.
				 */
				preg_match("/`([^\\r\\n`]+)`\.`{0,1}([^\\r\\n`]+)`{0,1}/", $column, $matches);
				$this->orderBy[$matches[1]][$matches[2]] = $direction;

			} else if ($this->columns && in_array($column, array_column($this->columns, "columnAlias"))){
				$this->orderBy[][$column] = $direction;

			} else if(!in_array($column, array_column($this->getTableMetadata($table, true),"COLUMN_NAME"))){
				//Ensure you only order by columns that actually exist
				continue;
			} else {
				$this->orderBy[$alias][$column] = $direction;
			}
		}

		return true;
	}

	/**
	 * Returns a string of order bys.
	 * Can be limited to a given table alias, or
	 * a given table alias can be ignored.
	 *
	 * @param string|int|array $thisTableAliasOnly Only return order bys for this one (or if array, many) aliases
	 * @param string|int|array $ignoreTableAlias Only return order bys for tables except this one (or if array, many) aliases
	 *
	 * @return bool|string
	 */
	private function getOrderBy($thisTableAliasOnly = NULL, $ignoreTableAlias = NULL){
		if(!is_array($this->orderBy)){
			return false;
		}
		foreach($this->orderBy as $alias => $columns){
			if($thisTableAliasOnly){
				//if the table alias is to be limited
				if(is_array($thisTableAliasOnly)){
					//if there are a few aliases to include
					if(!in_array($alias, $thisTableAliasOnly)){
						//if there is no match on any of the aliases
						continue;
						//ignore this alias (and it's order bys)
					}
				} else {
					//if there is only one alias to match on
					if($alias != $thisTableAliasOnly){
						//if there is no match
						continue;
						//ignore this alias (and it's order bys)
					}
				}
			}
			if($ignoreTableAlias){
				//if the table alias is to be limited
				if(is_array($ignoreTableAlias)){
					//if there are a few aliases to include
					if(in_array($alias, $ignoreTableAlias)){
						//if there is a match on any of the aliases
						continue;
						//ignore this alias (and it's order bys)
					}
				} else {
					//if there is only one alias to match on
					if($alias == $ignoreTableAlias){
						//if there is a match
						continue;
						//ignore this alias (and it's order bys)
					}
				}
			}
			foreach($columns as $column => $direction){
				if(is_int($alias)){
					if($this->isFunction($column)){
						$return[] = "{$column} {$direction}";
					} else {
						$return[] = "`{$column}` {$direction}";
					}

				} else {
					$return[] = "`{$alias}`.{$column} {$direction}";
					//`` are not surrounding columns by design
				}
			}
		}

		if(!is_array($return)){
			return false;
		}

		return "ORDER BY ".implode(",\r\n", $return);
	}

	private $groupBy;
	private $needsGroupBy;

	/**
	 * Returns a string of group-by columns
	 * if they have been defined or requested.
	 * Otherwise, will return FALSE.
	 *
	 * @param null $thisTableAliasOnly
	 * @param null $ignoreTableAlias
	 *
	 * @return bool|string
	 */
	private function getGroupBy($thisTableAliasOnly = NULL, $ignoreTableAlias = NULL){
		if($this->needsGroupBy && empty($this->groupBy)){
			//if this query needs a group by, by none has been assigned
			$this->autoGroupBy();
		}

		if(empty($this->groupBy)){
			return false;
		}

//		$filteredColumns = array_filter($this->groupBy, function ($column) use ($thisTableAliasOnly, $ignoreTableAlias) {
//			# Only include
//			if (is_array($thisTableAliasOnly)) {
//				//if there are many tables to include
//				return (in_array($column['tableAlias'], $thisTableAliasOnly));
//			} else if ($thisTableAliasOnly) {
//				//if there is only one table to include
//				return ($column['tableAlias'] == $thisTableAliasOnly);
//			}
//
//			# Only exclude
//			if (is_array($ignoreTableAlias)) {
//				//if there are many tables to exclude
//				return (!in_array($column['tableAlias'], $ignoreTableAlias));
//			} else if ($ignoreTableAlias) {
//				//if there is only one table to exclude
//				return ($column['tableAlias'] != $ignoreTableAlias);
//			}
//			return true;
//		});

		if(!$filteredColumns = $this->filterIncludeExclude($this->groupBy, $thisTableAliasOnly, $ignoreTableAlias)){
			return false;
		}

		foreach($filteredColumns as $column){
			$groupBy[] = "`{$column['tableAlias']}`.`{$column['columnName']}`";
		}

		return "GROUP BY ".implode(",\r\n", $groupBy);

//		return "GROUP BY ".implode(",\r\n", array_column($filteredColumns, "columnAlias"));

//		if(is_array($this->groupBy)) {
//			foreach ($this->groupBy as $alias => $columns) {
//
//
//				foreach ($columns as $column) {
//					$groupBy[] = "`{$column}`";
//				}
//			}
//
//			return "GROUP BY ".implode(",\r\n", $groupBy);
//		}
//
//		return false;
	}

	/**
	 * Automatically adds all columns to the group-by that are NOT functions.
	 *
	 * @param null $thisTableAliasOnly
	 * @param null $ignoreTableAlias
	 *
	 * @return bool
	 */
	private function autoGroupBy($thisTableAliasOnly = NULL, $ignoreTableAlias = NULL){
		if(!is_array($this->getColumns(false, $thisTableAliasOnly, $ignoreTableAlias))){
			return true;
		}
		foreach($this->getColumns(false, $thisTableAliasOnly, $ignoreTableAlias) as $column){
			if(!$this->isFunction($column['columnName'])){
//				$this->groupBy[$column['tableAlias']][] = $column['columnAlias'];
//				$this->groupBy[] = [
//					"tableName" => $column['tableName'],
//					"tableAlias" => $column['tableAlias'],
//					"columnName" => NULL,
//					"columnAlias" => $column['columnAlias'],
//					"columnWithAlias" => NULL,
//				];
				$this->groupBy[] = $column;
			}
		}
		return true;
	}

	/**
	 * Sets a group-by value based on table.
	 *
	 * @param      $gb
	 * @param null $table
	 * @param null $alias
	 *
	 * @return bool
	 */
	private function setGroupBy($gb, $table = NULL, $alias = NULL){

		if(!$table){
			//if there is no table submitted, assume it's the main table
			$table = $this->table['name'];
		}

		if(!$alias){
			//if there is no alias submitted, assume it's the main table alias
			$alias = $this->table['alias'];
		}

		/**
		 * The below shouldn't need to happen,
		 * because as soon as a function is used,
		 * a group by is automatically generated
		 * from the rest of the columns.
		 */

		# Auto group columns
		if($gb === TRUE){
			/**
			 * If group_by is set to TRUE, it means that all columns
			 * that are NOT functions should be grouped.
			 */
			$this->autoGroupBy();
			return true;
		}

		# If a single column is to be grouped
		if(is_string($gb)){
			if(in_array($gb, array_column($this->getColumns(), "columnAlias"))){
				//if the column to be grouped exists as a column that is requested
//				$this->groupBy[$alias][] = $gb;
				$this->groupBy[] = [
					"tableName" => $table,
					"tableAlias" => $alias,
					"columnName" => NULL,
					"columnAlias" => $gb,
					"columnWithAlias" => NULL,
				];
			} else {
				throw new \mysqli_sql_exception("The <code>{$gb}</code> column isn't being selected, thus cannot be used as a group by.");
			}
			return true;
		}

		# If an array of columns are to be grouped
		if(is_array($gb)){
			$columns = $this->getColumns();
			foreach($gb as $column){
				if (($key = array_search($column, array_column($columns, "columnAlias"))) !== FALSE) {
					//if the column to be grouped exists among the columns in the SELECT clause
//					$this->groupBy[$columns[$key]['tableAlias']][] = $column;
					$this->groupBy[] = [
						"tableName" => $columns[$key]['tableName'],
						"tableAlias" => $columns[$key]['tableAlias'],
						"columnName" => NULL,
						"columnAlias" => $column,
						"columnWithAlias" => NULL,
					];
				} else {
					throw new \mysqli_sql_exception("The <code>{$gb}</code> column isn't being selected, thus cannot be used as a group by.");
				}
				return true;
			}
		}

		return true;
	}

	/**
	 * Disconnects from the mySQL server.
	 * @return bool
	 */
	function disconnect(){
		if($this->mysqli){
			//if a connection has been opened
			$this->mysqli->close();
//			unset($this->mysqli);
		}
		return true;
	}
}