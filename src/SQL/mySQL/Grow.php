<?php


namespace App\Common\SQL\mySQL;

use App\Common\str;
use TypeError;

/**
 * Class Grow
 * @package App\Common\SQL\mySQL
 */
class Grow extends Common {
	/**
	 * Given a table name, check if all the columns in the $data array
	 * exist in the table, and that the table columns are wide enough.
	 * If not, create the column, make sure it's wide enough.
	 *
	 * @param array $table
	 * @param array $set
	 *
	 * @return bool
	 */
	public function growTable(array $table, array $set){
		# Get the table metadata
		if($this->tableExists($table['db'], $table['name'], $table['is_tmp'])){
			//if table exists
			$tableMetadata = $this->getTableMetadata($table, true, false);
		} else {
			//if table doesn't exist
			$tableMetadata = $this->createTable($table);
		}

		# Get the columns a user cannot alter
		$columns_to_ignore = $this->getTableColumnsUsersCannotUpdate($table['name']);

		# The $set array can be one or many rows of data
		$data = str::isNumericArray($set) ? $set : [$set];

		# For each row
		foreach($data as $row){
			# Each row needs to be an array
			if(!is_array($row)){
				continue;
			}

			# For each column of data that is to be inserted
			foreach($row as $col => $val) {
				if(in_array($col, $columns_to_ignore)){
					//If the column to check cannot be updated by the user, ignore it
					continue;
				}
				if(!$val){
					//If the $val has no value, ignore this column
					continue;
				}

				if(is_array($val)){
					//If array values have accidentally been included, ignore them.
					throw new TypeError("The <code>{$col}</code> column in the <code>{$table['name']}</code> table has array data as its value.".print_r($data,true));
				}

				# Get the column data type based on the current value
				$datatype = $this->identifyValDatatype($val);

				# Get the column add/change query
				if(!$query = $this->getQuery($table, $col, $val, $datatype, $tableMetadata)){
					//if no change is required
					continue;
				}

				# Run the update query
				$sql = new Run($this->mysqli);
				$sql->run($query);

				# Update the metadata (as a new/altered column has just been added)
				if(!$tableMetadata = $this->getTableMetadata($table, true, false)){
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Creates a blank table based on a table name alone.
	 *
	 * @param array $table
	 *
	 * @return array|null
	 */
	private function createTable (array $table): ?array
	{
		$query = /** @lang MySQL */"
		CREATE TABLE `{$table['db']}`.`{$table['name']}` (
			`{$table['name']}_id` CHAR(36) NOT NULL COLLATE 'utf8mb4_unicode_ci',
			`created` DATETIME NULL DEFAULT NULL,
			`created_by` CHAR(36) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
			`updated` DATETIME NULL DEFAULT NULL,
			`updated_by` CHAR(36) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
			`removed` DATETIME NULL DEFAULT NULL,
			`removed_by` CHAR(36) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
			PRIMARY KEY (`{$table['name']}_id`) USING BTREE
		)
		COLLATE='utf8mb4_unicode_ci'
		ENGINE=InnoDB
		;
		";

		# Run the create query
		$sql = new Run($this->mysqli);
		$sql->run($query);

		# Update the metadata
		$tableMetadata = $this->getTableMetadata($table, true, false);

		return $tableMetadata;
	}

	/**
	 * Given a value, tries to identify what data type it is.
	 * It will try to recognise the following types:
	 * 	- int
	 * 	- bigint
	 * 	- decimal
	 * 	- text
	 * 	- varchar (default)
	 *
	 * @param string $val
	 *
	 * @return string
	 */
	private function identifyValDatatype(string $val){
		if($val == "NOW()"){
			return "datetime";
		}

		if($val == "CURDATE()"){
			return "date";
		}

		# DATE (YYYY-MM-DD) is still treated as a varchar
		if(preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $val)){
			return 'varchar';
		}

		# JSON
		if(str::isJson($val)){
			return 'json';
		}

		# UUID
		if(str::isUuid($val)){
			return 'char';
		}

		# INT, BIGINT
		if(preg_match("/^-?[0-9]$/", $val)
			&& substr($val, 0,1) != "0"){
			//Only (positive or negative) integers
			//and cannot start with a zero

			# Get the type of int
			if(strlen($val) < 10){
				return "int";
			} else if(strlen($val) < 19){
				return "bigint";
			} else {
				//if the number is bigger than 18 characters, treat it as a varchar string
				return "varchar";
			}
		}

		# DECIMAL
		else if(preg_match("/^-?[0-9](\.[0-9]+)?$/", $val)
			&& substr($val, 0,1) != "0"){
			//Only numbers
			//and cannot start with a zero
			return "decimal";
		}

		# TEXT
		else if(strlen($val) > 500) {
			//If the column is particularly long
			return "text";
		}

		# VARCHAR
		else {
			return "varchar";
		}
	}

	/**
	 * Get the query for adding/change the query
	 * @param string $table
	 * @param string $col
	 * @param        $val
	 * @param string $type
	 * @param array  $tableMetadata
	 *
	 * @return bool|string
	 */
	private function getQuery(array $table, string $col, $val, string $type, ?array $tableMetadata = []){
		# Get the key for the existing column, if it exists
		if(is_array($tableMetadata)){
			$key = array_search($col, array_filter(array_combine(array_keys($tableMetadata), array_column($tableMetadata, 'COLUMN_NAME'))));
		} else {
			$key = false;
		}
		
		# If the column doesn't exist
		if($key === false){
			//if the column doesn't exist at all, easy, add it
			return $this->getNewColumnQuery($table, $col, $val, $type, $tableMetadata);
		}

		# If the column exists, but for a different data type
		if($tableMetadata[$key]['DATA_TYPE'] != $type) {
			return $this->getChangeColumnQuery($table, $col, $val, $type, $tableMetadata);
		}

		# If the column exists and is the same data type
		return $this->getGrowColumnQuery($table, $col, $val, $type, $tableMetadata);
	}

	/**
	 * Generates the new column query.
	 *
	 * @param array $table
	 * @param string $col
	 * @param        $val
	 * @param string $type
	 * @param array  $tableMetadata
	 *
	 * @return string
	 */
	private function getNewColumnQuery(array $table, string $col, $val, string $type, ?array $tableMetadata = []){
		# Get the key for the last column, if it cannot be found, assume table contains no (editable) columns
		if(($key = array_key_last($tableMetadata)) === NULL){
			$last_column = $table["id_col"];
		} else {
			$last_column = $tableMetadata[$key]['COLUMN_NAME'];
		}

		# Prepare the type
		$type = $this->prepareType($type, $val);

		# Write the query to create a new column
		return "ALTER TABLE `{$table['db']}`.`{$table['name']}` ADD COLUMN `{$col}` {$type} NULL DEFAULT NULL AFTER `$last_column`;";
	}

	/**
	 * Query for when the column has to (potentially) change data type.
	 *
	 * @param array $table
	 * @param string $col
	 * @param        $val
	 * @param string $type
	 * @param array  $tableMetadata
	 *
	 * @return bool|string
	 */
	private function getChangeColumnQuery(array $table, string $col, $val, string $type, array $tableMetadata){
		# Get the key for the (existing) column
		$key = array_search($col, array_filter(array_combine(array_keys($tableMetadata), array_column($tableMetadata, 'COLUMN_NAME'))));

		# If the current type is BIGINT
		if($tableMetadata[$key]['DATA_TYPE'] == "bigint"){
			if($type == "int"){
				//bigint takes int
				return false;
			}
		}
		# If the current type is JSON
		if($tableMetadata[$key]['DATA_TYPE'] == "json"){
			//Leave JSON alone
			return false;
		}

		# If the current type is INT
		//Change it

		# If the current type is DECIMAL
		if($tableMetadata[$key]['DATA_TYPE'] == "decimal"){
			# INT and BIGINT could potentially fit in the decimal, if they're small enough
			if(in_array($type, ["int","bigint"])){
				if(strlen($val) <= $tableMetadata[$key]['NUMERIC_PRECISION']){
					//if the (big)int fits in the decimal column
					return false;
				} else {
					$type = "{$tableMetadata[$key]['DATA_TYPE']}(".strlen($val).",{$tableMetadata[$key]['NUMERIC_SCALE']})";
				}
			}
		}

		# If the current type is TEXT
		if($tableMetadata[$key]['DATA_TYPE'] == "text"){
			//once text, doesn't matter what new rows are, they will always remain text
			return false;
		}

		# If the current type is VARCHAR
		if($tableMetadata[$key]['DATA_TYPE'] == "varchar"){
			if(strlen($val) <= $tableMetadata[$key]['CHARACTER_MAXIMUM_LENGTH']){
				//if the value fits in the varchar column
				return false;
			} else {
				$type = "{$tableMetadata[$key]['DATA_TYPE']}(".strlen($val).")";
			}
		}

		# Prepare the type
		$type = $this->prepareType($type, $val, $tableMetadata[$key]);

		# Return the query
		return "ALTER TABLE `{$table['db']}`.`{$table['name']}` CHANGE COLUMN `{$col}` `{$col}` {$type};";
	}


	/**
	 * Query for when the column (potentially) has to grow.
	 *
	 * @param array $table
	 * @param string $col
	 * @param        $val
	 * @param string $type
	 * @param array  $tableMetadata
	 *
	 * @return bool|string
	 */
	private function getGrowColumnQuery(array $table, string $col, $val, string $type, array $tableMetadata){
		if(in_array($type, ["int", "bigint", "text", "datetime", "json"])){
			//If the column data type is any of these, no need to change their lengths
			return false;
		}

		# Get the key for the existing column
		$key = array_search($col, array_filter(array_combine(array_keys($tableMetadata), array_column($tableMetadata, 'COLUMN_NAME'))));

		# DECIMAL
		if($type == "decimal") {
			$precision = strlen(explode(".", $val)[0]);
			$scale = strlen(explode(".", $val)[1]);

			if($precision <= $tableMetadata[$key]['NUMERIC_PRECISION']
			&& $scale <= $tableMetadata[$key]['NUMERIC_SCALE']){
				//If the new row is not bigger than the existing column specs
				return false;
			}
		}

		# VARCHAR
		if($type == "varchar"){
			if(strlen($val) <= $tableMetadata[$key]['CHARACTER_MAXIMUM_LENGTH']){
				//If the new row is not bigger than the existing column specs
				return false;
			}
		}

		# Prepare the type
		$type = $this->prepareType($type, $val, $tableMetadata[$key]);

		# Return the query
		return "ALTER TABLE `{$table['db']}`.`{$table['name']}` CHANGE COLUMN `{$col}` `{$col}` {$type};";
	}

	/**
	 * Prepares the data type string.
	 *
	 * @param string $type
	 * @param        $val
	 * @param array|bool  $existing_column
	 *
	 * @return string
	 */
	private function prepareType(string $type, $val, array $existing_column = NULL){
		# DECIMAL
		if($type == "decimal"){
			$precision = strlen(explode(".", $val)[0]);
			$scale = strlen(explode(".", $val)[1]);
			if($existing_column && $precision < $existing_column['NUMERIC_PRECISION']) {
				$precision = $existing_column['NUMERIC_PRECISION'];
			}
			$precision += $scale;
			//decimal(18, 10) means 10 digits after decimal and 8 before decimal, not until 18 digits
			$type .= "({$precision},{$scale})";
		}

		# VARCHAR
		if(in_array($type, ["varchar", "char"])){
			if($existing_column){
				//If there is an existing column to compare against
				if($existing_column['CHARACTER_MAXIMUM_LENGTH']){
					//If that column already is string column
					$existing_length = $existing_column['CHARACTER_MAXIMUM_LENGTH'];
				} else if($existing_column['NUMERIC_PRECISION']){
					//If that column is a numerical column
					$existing_length = $existing_column['NUMERIC_PRECISION'];
				}
			}
			if(strlen($val) < $existing_length){
				//make sure we don't accidentally _shrink_ the column
				$type .= "(".$existing_length.")";
			} else {
				$type .= "(".strlen($val).")";
			}
		}

		return $type;
	}
}