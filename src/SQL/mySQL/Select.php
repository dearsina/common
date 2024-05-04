<?php


namespace App\Common\SQL\mySQL;


use App\Common\SQL\DotNotation;
use App\Common\str;

/**
 * Generates a SQL SELECT query, executes it and returns the results.
 * Methods that are private are only used in this class.
 * Methods that are protected are referenced in the Common class.
 * Methods that are public can be called from the outside.
 *
 * @package App\Common\SQL\mySQL
 */
class Select extends Common {
	/**
	 * Generate a SQL SELECT query, execute it and return the results.
	 *
	 * @param array $a
	 * @param null  $return_query
	 *
	 * @return string|array
	 */
	public function select(array $a, $return_query = NULL)
	{
		extract($a);

		$this->setCTEs($cte);

		# Set the (main) table (needs to be done first)
		$this->setTable($db, $table, $id, $include_removed, $count);

		# Load the table metadata for this table
		$this->getTableMetadata($this->table);

		# Set distinct (optional)
		$this->setDistinct($distinct);

		# Set columns
		$this->setColumns($columns, $include_meta);

		# Set joins
		$this->setJoins("INNER", $join);
		$this->setJoins("LEFT", $left_join);

		# Set where
		$this->setWhere($where, $or);

		# Set group by
		$this->setGroupBy($group_by);

		# Set having
		$this->setHaving($having);

		# Set order by
		$this->setOrderBy($order_by);

		# Set limits
		$this->setLimit($limit, $start, $length, $offset);

		# Generate the query with a sub-query if needed
		if($this->limit && $this->join){
			// If there are joins (with columns) and a limit, we need to use a sub-query

			# Go through each join and check if there are columns
			foreach($this->join as $joins){
				foreach($joins as $join){
					if($join['columns'] !== NULL){
						// If there are columns, we need to use a sub-query
						$query = $this->generateLimitedJoinQuery();
						break 2;
					}
				}
			}
		}

		# Otherwise, generate a normal query
		if(!$query){
			$query = $this->generateQuery();
		}

		# If output is to be stored in a tmp table
		if($tmp){
			$query = "CREATE TEMPORARY TABLE IF NOT EXISTS `{$tmp}` AS ({$query})";
		}

		# If only the SQL is requested
		if($return_query){
			return $query;
		}

		# If we're to export the result
		if($export){
			$sql = new Export($this->mysqli, $export);
			return $sql->export($query);
		}

		# Execute the SQL
		$sql = new Run($this->mysqli);
		$results = $sql->run($query);

		# If the output is stored in a tmp table, return how many rows were affected (created)
		if($tmp){
			return $results['affected_rows'];
		}

		# No results found
		if(!$results['num_rows']){
			//if no rows are found, return NULL

			if(str::isDev()){
				# Add the time it took to run the query also
				$last_query = array_pop($_SESSION['queries']);
				$last_query['time'] = str::stopTimer($_SESSION['query_timer']);
				$last_query['time_since_start'] = str::stopTimer();
				$last_query['rows'] = 0;
				$_SESSION['queries'][] = $last_query;
			}

			return NULL;
		}

		# Count (only) requested
		if($count){
			//if (only) the row count is requested
			return $results['rows'][0]['C'];
		}

		# JSON decode (where applicable)
		$this->jsonDecode($results['rows']);
		/**
		 * Has to be done before dot-notation normalisation
		 * because to identify JSON columns, it uses column
		 * aliases using dot-notation.
		 */

		# Normalise, unless requested not to
		if(!$flat){
			$rows = DotNotation::normalise($results['rows']);
		}
		else {
			$rows = $results['rows'];
		}
		# TODO Somehow fix bug where normalisation messes with JSON column data

		# Add a bunch of metadata (if in dev)
		if(str::isDev()){
			$last_query = array_pop($_SESSION['queries']);
			$last_query['time'] = str::stopTimer($_SESSION['query_timer']);
			$last_query['time_since_start'] = str::stopTimer();
			$last_query['rows'] = count($results['rows']);
			$_SESSION['queries'][] = $last_query;
		}

		if(!$rows){
			return NULL;
		}

		# Return without the root numeric array
		if($id || ($limit == 1)){
			/**
			 * If a specific main table ID has been given,
			 * or if the limit has been set to 1, only return
			 * the first/a single row.
			 */
			return reset($rows);
		}

		# Otherwise, and most commonly, return all the result rows
		return $rows;
	}

	protected function setCTEs(?array $ctes): void
	{
		if(!$ctes){
			return;
		}

		foreach($ctes as $alias => $cte){
			$select = new Select($this->mysqli);
			$this->ctes[$alias]['query'] = $select->select($cte, true);
			$this->ctes[$alias]['columns'] = $select->getAllColumns();
		}
	}

	protected function getCTEs(): ?string
	{
		if(!$this->ctes){
			return NULL;
		}

		foreach($this->ctes as $alias => $cte){
			$cte = str_replace("\r\n", "\r\n\t", $cte['query']);
			$ctes[] = "`{$alias}` AS (\r\n\t{$cte}\r\n)";
		}

		return "WITH ".implode(", ", $ctes);
	}

	/**
	 * If a column is a JSON column,
	 * json decode the value
	 * into a PHP array.
	 *
	 * @param array|null $rows
	 */
	private function jsonDecode(?array &$rows): void
	{
		# Find all columns that are of the JSON data-type
		foreach($this->getAllColumns() as $column){
			if($column['json']){
				$json_columns[] = $column['alias'] ?: $column["name"];
			}
		}

		# If none, do nothing
		if(!$json_columns){
			return;
		}

		# For each JSON column, json_decode the value
		foreach($rows as $id => $row){
			foreach($row as $col_name => $col_value){
				if(in_array($col_name, $json_columns)){
					$rows[$id][$col_name] = json_decode($col_value, true);
				}
			}
		}
	}

	/**
	 * If a table has joins, and a limit on rows, the cap
	 * may inadvertently split the joined rows
	 * belonging to a single main table.
	 *
	 * To mitigate this, we move the limit to only account for
	 * main table rows, and attach as many rows as those
	 * main table rows require.
	 *
	 * To further complicate matters, if a join has a where clause,
	 * that join (and any links in the chain between the join and
	 * the main table) need to be brought in to the sub-query.
	 *
	 * It's faffy, but it's imperative that it works like this,
	 * otherwise, chunked requests will miss data.
	 *
	 * @return string
	 */
	private function generateLimitedJoinQuery(): string
	{
		# Include the main table
		$this->setTableAliasWithWhere([$this->table['alias']]);
		/**
		 * We want the table_alias_with_where array to contain all table
		 * aliases that need to be inside the sub-query. The main
		 * table is (obviously) the most important.
		 */

		# Get any CTEs that feed into the tables
		$table[] = $this->getCTEs();

		# Generate table sub-query
		$table[] = "SELECT";
		$table[] = $this->getDistinctSQL();
		$table[] = $this->getColumnsSQL($this->getTableAliasWithWhereAndOrder());
		$table[] = $this->getTableSQL();
		$table[] = $this->getJoinsSQL(true, false);
		$table[] = $this->getWhereSQL($this->getTableAliasWithWhereAndOrder());
		$table[] = $this->getGroupBySQL($this->getTableAliasWithWhereAndOrder());
		$table[] = $this->getHavingSQL($this->getTableAliasWithWhereAndOrder());
		$table[] = $this->getOrderBySQL($this->getTableAliasWithWhereAndOrder());
		$table[] = $this->getLimitSQL();

		# Generate query (with sub-query)
		$query[] = "SELECT";
		$query[] = $this->getDistinctSQL();
		$query[] = $this->getColumnsSQL(NULL, NULL, $this->getTableAliasWithWhereAndOrder());
		$query[] = $this->getTableSQL(implode("\r\n", array_filter($table)));
		$query[] = $this->getJoinsSQL(false, true);
		$query[] = $this->getWhereSQL(NULL, $this->getTableAliasWithWhereAndOrder());
		$query[] = $this->getGroupBySQL(NULL, $this->getTableAliasWithWhereAndOrder());
		$query[] = $this->getHavingSQL(NULL, $this->getTableAliasWithWhereAndOrder());
		$query[] = $this->getOrderBySQL(NULL, $this->getTableAliasWithWhereAndOrder());

		return implode("\r\n", array_filter($query));
	}

	/**
	 * Build a standard query.
	 *
	 * @return string
	 */
	private function generateQuery(): string
	{
		# Get any CTEs that feed into the tables
		$query[] = $this->getCTEs();

		$query[] = "SELECT";
		$query[] = $this->getDistinctSQL();
		$query[] = $this->getColumnsSQL();
		$query[] = $this->getTableSQL();
		$query[] = $this->getJoinsSQL();
		$query[] = $this->getWhereSQL();
		$query[] = $this->getGroupBySQL();
		$query[] = $this->getHavingSQL();
		$query[] = $this->getOrderBySQL();
		$query[] = $this->getLimitSQL();
		return implode("\r\n", array_filter($query));
	}

	public function groupConcat(array $table, ?string $col_alias, array $a): array
	{
		extract($a);

		$this->table = $table;

		# Set distinct (optional)
		$this->setDistinct($distinct);

		# Set columns
		$this->setColumns($columns);

		# Set order by
		$this->setOrderBy($order_by);

		# Set separator
		$this->setSeparator($separator);

		$query[] = $this->getDistinctSQL();
		$query[] = $this->getColumnsSQL();
		$query[] = $this->getOrderBySQL();
		$query[] = $this->getSeparatorSQL();

		return [
			"table_alias" => $table['alias'],
			"name" => implode(" ", array_filter($query)),
			"alias" => $col_alias,
			"agg" => "GROUP_CONCAT",
		];
	}

	private function getSeparatorSQL(): ?string
	{
		if(!$this->separator){
			return NULL;
		}
		return "SEPARATOR '{$this->separator}'";
	}

	private function getOrderBySQL(?array $alias_only = NULL, ?array $except_alias = NULL): ?string
	{
		$order_bys[$this->table['alias']] = $this->order_by;

		# From any joins
		if(is_array($this->join)){
			foreach($this->join as $join_type => $joins){
				foreach($joins as $join){
					if($join['order_by']){
						$order_bys[$join['table']['alias']] = $join['order_by'];
					}
				}
			}
		}

		foreach($order_bys as $alias => $conditions){
			if($alias_only){
				if(!in_array($alias, $alias_only)){
					continue;
				}
			}
			if($except_alias){
				if(in_array($alias, $except_alias)){
					continue;
				}
			}
			$order_by = array_merge($order_by ?: [], $conditions ?: []);
		}

		if(!count($order_by ?: [])){
			//if there are no order by, ignore
			return NULL;
		}

		return "ORDER BY " . implode(",\r\n", $order_by);
	}

	/**
	 * Get all group by rows.
	 *
	 * @param string|null $alias_only Can be limited by a given table alias.
	 *
	 * @return string|null
	 */
	private function getGroupBySQL(?array $alias_only = NULL, ?array $except_alias = NULL): ?string
	{
		$columns = $this->getAllColumns($alias_only, $except_alias);

		$strings = [];

		# If there are aggregate columns to group by around, or if one of the having clauses is an aggregate function
		if(array_filter(array_column($columns, "agg")) || $this->havingIsAggregate()){
			foreach($columns as $id => $column){
				if($column['agg']){
					continue;
				}
				$strings[$id] .= $column['table_alias'] ? "`{$column['table_alias']}`." : NULL;
				$strings[$id] .= $column['name'] ? "`{$column['name']}`" : NULL;
			}
		}

		# If there are custom group bys
		if($this->group_by){
			# Go through each group by value
			foreach($this->group_by as $group_by){
				# Compare each value to the column names
				foreach($columns as $column){
					# If there is a match, pull in the table alias and column name wrapped in backticks
					if($column['name'] == $group_by){
						$string = $column['table_alias'] ? "`{$column['table_alias']}`." : NULL;
						$string .= $column['name'] ? "`{$column['name']}`" : NULL;
						$strings[] = $string;
						continue 2;
					}
				}
				# Otherwise, just use the value as is
				$strings[] = $group_by;
			}
		}

		if(!$strings){
			return NULL;
		}

		return "GROUP BY " . implode(",\r\n", $strings);
	}

	private function getHavingSQL(?array $alias_only = NULL, ?array $except_alias = NULL): ?string
	{
		if(!$this->having){
			return NULL;
		}

		return "HAVING " . implode(" AND ", $this->having);
	}

	/**
	 * Collects all the columns from the main table and any joined tables and creates
	 * a single block of text with a column on each line.
	 *
	 * @param array|null $table_alias_only   Can be limited to a certain table alias
	 * @param array|null $except_table_alias A certain table alias can be excluded
	 * @param array|null $alias_only
	 *
	 * @return string
	 */
	private function getColumnsSQL(?array $table_alias_only = NULL,
								   ?array $except_table_alias = NULL,
								   ?array $alias_only = NULL)
	{
		# Get all columns
		$columns = $this->getAllColumns($table_alias_only, $except_table_alias);

		# If there are no columns, and the table is a CTE, load the CTE columns
		if($this->table['is_cte']){
			foreach($this->table['is_cte']['columns'] as $column){
				$column['table_alias'] = $this->table['name'];
				$columns[] = $column;
			}
		}
		// Not 100% sure why they weren't loaded in the first place

		# Format all the columns
		if(!$formatted_columns = $this->formatColumns($columns, $alias_only)){
			if(!$table_alias_only && !$except_table_alias){
				//If no filters were applied, and there still were not columns

				# Check if the given table name exists at all
				if($dbs = $this->getSchemasFromTableName($this->table['name'])){
					// if the table name exists
					if(in_array($this->table['db'], $dbs)){
						// If the database given is correct (but there are no columns)
						throw new \mysqli_sql_exception("The SELECT query doesn't have any columns allocated to the <code>{$this->table['name']}</code> table.");
					}
					else {
						# As the table _is_ found (but in a different database), inform them so that they can correct it
						throw new \mysqli_sql_exception("The <code>{$table["name"]}</code> table can be found in the <code>".str::oxfordImplode($dbs)."</code> ".str::pluralise_if($dbs, "database").", not the <code>{$table["db"]}</code> database. Please address.");
					}
				}

				# If the table name doesn't seem to exist in any schema
				else {
					throw new \mysqli_sql_exception("The <code>{$this->table['name']}</code> table does not seem to exist. Are you sure you have the correct table name?");
				}
			}
		}

		return implode(",\r\n", $formatted_columns);
	}

	private function getAllColumns(?array $alias_only = NULL, ?array $except_alias = NULL): ?array
	{
		$alias_columns = [];
		$columns = [];

		$alias_columns[$this->table['alias']] = $this->columns;
		if(is_array($this->join)){
			foreach($this->join as $type => $joins){
				foreach($joins as $join){
					if(!$join['columns']){
						continue;
					}
					foreach($join['columns'] as $column){
						$column['parent_alias'] = $join['table']['parent_alias'];
						$alias_columns[$join['table']['alias']][] = $column;
					}
				}
			}
		}

		# Optional table alias filters
		foreach($alias_columns as $alias => $cols){
			if($alias_only){
				if(!in_array($alias, $alias_only)){
					continue;
				}
			}
			if($except_alias){
				if(in_array($alias, $except_alias)){
					continue;
				}
			}
			$columns = array_merge($columns ?: [], $cols ?: []);
		}

		return $columns;
	}

	/**
	 * Given a batch of columns from a single table, format them and return an array of column strings.
	 *
	 * @param array|null $columns
	 * @param array|null $alias_only
	 *
	 * @return array|null
	 */
	private function formatColumns(?array $columns, ?array $alias_only = NULL): ?array
	{
		if(!is_array($columns)){
			return NULL;
		}

		foreach($columns as $id => $column){
			if($alias_only && in_array($column['table_alias'], $alias_only)){
				/**
				 * If the table has already been formatted in a sub-query,
				 * and all we need to do is reference the alias only.
				 */
				if($column['table_alias'] == $this->table['alias']){
					//if this is the main table
					$strings[$id] = $column['table_alias'] ? "`{$column['table_alias']}`." : NULL;
					//Prefix the column with the ID to avoid ambiguous column name situations
				}
				$strings[$id] .= $column['alias'] ? "`{$column['alias']}`" : "`{$column['name']}`";
				//If there is no alias, just use the column name
				continue;
			}

			$strings[$id] = $column['table_alias'] ? "`{$column['table_alias']}`." : NULL;

			# Column name
			if($column['agg'] == "GROUP_CONCAT"){
				//GROUP_CONCAT columns have already been formatted
				$strings[$id] = $column['name'];
			}
			else if($column['name'] == "*"){
				//* value does not need to be enclosed in `
				$strings[$id] .= $column['name'];
			}
			else if($column['name']){
				//For everyone else, enclose in `
				$strings[$id] .= "`{$column['name']}`";
			}

			# Does the column have an aggregate function?
			if($column['agg']){
				$strings[$id] = "{$column['agg']}({$this->getDistinctSQL(true)}{$strings[$id]})";
			}

			# You can feed a complete custom string
			if($column['string']){
				if($column['table_alias'] != $this->table['alias']){
					// We're only interested in the string itself if it's not the main table
					$strings[$id] = $column['string'];
				}

				else {
					$strings[$id] .= "`{$column['alias']}`";
					// Otherwise, we'll just feed it the alias, and that should be good enough
				}
			}

			$strings[$id] .= $column['alias'] ? " '{$column['alias']}'" : NULL;
		}

		return $strings ? array_filter($strings) : NULL;
	}

	/**
	 * Sets the group by for instances where the
	 * class itself doesn't know what to group by.
	 *
	 * @param array|string|null $group_by
	 *
	 * @return void
	 */
	private function setGroupBy($group_by): void
	{
		if(!$group_by){
			return;
		}

		if(is_array($group_by)){
			$this->group_by = $group_by;
			return;
		}

		$this->group_by[] = $group_by;
	}

	/**
	 * Sets the having clause.
	 *
	 * @param $having
	 *
	 * @return void
	 */
	private function setHaving($having): void
	{
		if(!$having){
			return;
		}

		$this->having = array_filter($this->recursiveWhere($this->table, "AND", $having));
	}

	private function havingIsAggregate(): bool
	{
		if(!$this->having){
			return false;
		}

		# If any of the having conditions are aggregate functions
		foreach($this->having as $condition){
			if(preg_match("/^\b(?:".implode("|", self::SQL_AGGREGATE_FUNCTIONS).")\b/i", $condition)){
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array|null $order_by
	 */
	private function setOrderBy(?array $order_by): void
	{
		$this->order_by = $this->getOrderBy($this->table, $this->columns, $order_by);
	}

	/**
	 * @param array|string $columns
	 * @param bool|null    $include_meta If set to true, will include the created/by, updated/by, removed/by columns
	 *                                   also
	 */
	private function setColumns($columns, ?bool $include_meta = NULL): void
	{
		$this->columns = $this->getColumns($this->table, $columns, $include_meta);
	}

	/**
	 * @param bool|null $agg_function
	 *
	 * @return string|null
	 */
	private function getDistinctSQL(?bool $agg_function = NULL): ?string
	{
		# If the call is explicitly for an aggregate function
		if($agg_function){
			return $this->distinct ? "DISTINCT " : NULL;
		}

		# If there is an aggregate column, don't issue the DISTINCT in the header
		if(array_filter(array_column($this->getAllColumns(), "agg"))){
			//if any of the columns are an aggregate function
			return NULL;
		}

		# If a count has been requested, don't issue the distinct outside the COUNT()
		if($this->table['count']){
			return NULL;
		}

		return $this->distinct ? "DISTINCT" : NULL;
	}

	/**
	 * @param bool $distinct
	 */
	private function setDistinct(?bool $distinct): void
	{
		$this->distinct = (bool)$distinct;
	}

	/**
	 * @param string|null $separator
	 */
	private function setSeparator(?string $separator): void
	{
		$this->separator = $separator;
	}

	/**
	 * Normalise a flat dot notation array.
	 *
	 * @param array  $rows The flat array
	 * @param string $dot  The character that separates the layers.
	 *
	 * @return array
	 */
	private function normalise(array $rows, string $dot = "."): array
	{
		# The last row's main table columns
		$last_main_table = [];

		# A collection of all the joined tables belonging to a given row of the main table
		$joined_tables = [];

		# For each row in the result set
		foreach($rows as $id => $row){

			# Contains this row's main table columns
			$main_table = [];

			# Contains this row's joined table columns
			$joined_table = [];

			# Filter away non-associative arrays (JSON value arrays)
			if(!is_array($row)){
				/**
				 * The logic here is that if the row is not an array,
				 * but instead is just a value, the whole $rows array
				 * is just a non-associative array value from a JSON
				 * column.
				 *
				 * The only reason it made here was because $rows _is_
				 * a numerical array and thus was correctly fed into
				 * the recursive loop.
				 */
				return $rows;
			}

			# Foreach column and value
			foreach($row as $col => $val){
				/**
				 * Whether a column name contains a period or not
				 * is the determining factor of whether the column
				 * belongs to the main table (no period) or a joined
				 * table (periods).
				 */
				if(!strpos($col, $dot)){
					//if the column belongs to the main table
					$main_table[$col] = $val;

				}
				else {
					//If the column belongs to a joined table

					# Remove the first database fragment in the alias
					$col_fragments = explode(":", $col);
					// Break up the alias by :
					if(count($col_fragments) > 1){
						//If there _are_ any :
						array_shift($col_fragments);
						//Remove the first (database) fragment
					}
					$col = implode(":", $col_fragments);
					/**
					 * Join the other pieces back together.
					 * We only deal with one database fragment layer
					 * at a time.
					 *
					 * EXPERIMENTAL, not sure what this will do to
					 * two tables with the same name from different
					 * databases.
					 *
					 * TODO Fix it so that tables that have the same
					 * DB as the main table are excused from db
					 * fragments in the aliases/
					 */

					# Break open the column name
					$col = explode($dot, $col);

					# Extract out the joined table, and the column names (that may include joined children)
					$joined_table[array_shift($col)][$id][implode($dot, $col)] = $val;
				}
			}

			# Ensure the main table actually contains some data
			if(!array_filter($main_table)){
				// If the main table has no values (it's completely empty)
				continue;
				// Forget it.
			}
			// This may have a negative impact on any tables that are joined without conditions
			// Because the assumption is that if tables are joined, they must have data in the row (that was used to join)


			# If this is the first row, or if this row's main columns are the same as the last row
			if(!$last_main_table || ($main_table == $last_main_table)){

				# Update the last main table variable (only useful for the first row)
				$last_main_table = $main_table;

				# merge all rows of columns belonging to joined tables
				$joined_tables = array_merge_recursive($joined_tables ?: [], $joined_table ?: []);

				# Go to the next row
				continue;
			}

			# At this point, the main columns are different from the previous row's main columns

			# Add the last row's main columns, merged with all of it's joined table rows to the normalised array
			$normalised[] = array_merge($last_main_table, $joined_tables);

			# Update the last main table variable
			$last_main_table = $main_table;

			# Reset the joined tables
			$joined_tables = [];

			# Add this row's joined table columns
			$joined_tables = array_merge_recursive($joined_tables ?: [], $joined_table ?: []);
		}

		# Capture the last row
		$normalised[] = array_merge($last_main_table, $joined_tables);

		# Go deeper
		foreach($normalised as $id => $row){
			//For each row that is now normalised
			foreach($row as $key => $val){
				//For each column value
				if(is_array($val)){
					//if any of the values are arrays, make sure that array is also normalised

					# Get the normalised value
					$normalised_val = $this->normalise($val);

					# Ensure the normalised array contains values
					if(empty(array_filter($normalised_val))){
						// If the normalised value is empty

						# Remove it
						unset($normalised[$id][$key]);

						# Jog on
						continue;
					}

					# Replace the value in the array with the normalised value
					$normalised[$id][$key] = $normalised_val;
				}
			}
		}

		# Remove rows that are exactly the same (including comparing their joined children)
		$normalised = array_map('unserialize', array_unique(array_map('serialize', $normalised)));
		//@link https://stackoverflow.com/a/44179213/429071
		// EXPERIMENTAL Not sure of the wider consequences of removing rows that are identical

		return $normalised;
	}
}