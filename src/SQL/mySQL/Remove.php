<?php


namespace App\Common\SQL\mySQL;


class Remove extends Common {
	public function remove(array $a, ?bool $return_query = NULL)
	{
		extract($a);

		# If the user_id has not been set (to either a particular user ID or to be ignored)
		if (!array_key_exists("user_id", $a)){
			global $user_id;
			if (!$user_id){
				throw new \Exception("Removing without a user ID is not allowed.");
			}
		}

		# Set the (main) table (needs to be done first)
		$this->setTable($db, $table, $id, $include_removed);

		# Set joins
		$this->setJoins("INNER", $join);
		$this->setJoins("LEFT", $left_join);

		# Set where
		$this->setWhere($where, $or);

		# Make sure the column or columns are identified
		if (!$id && !$this->whereConditionsExist()){
			throw new \Exception("An remove request was sent with no WHERE clause or column ID to identify what to remove.");
		}

		# Set limits
		$this->setLimit($limit, $start, $length);

		# Add ID, removed by and time
		$this->addRowMetadata($user_id);

		# Generate the query
		$query = $this->generateUpdateSQL();

		# If only the SQL is requested
		if ($return_query){
			return $query;
		}

		# Execute the SQL
		$sql = new Run($this->mysqli);
		return $sql->run($query);
		// Return the results that will contain some metadata about the results
	}

	/**
	 * Add row metadata + the given (or global) user_id of the author
	 *
	 * @param $user_id
	 */
	private function addRowMetadata($user_id): void
	{
		/**
		 * With REMOVE queries, we will never have more than one row in the SET
		 * array, but we're sharing methods with the INSERT query, where
		 * multiple SETs may occur.
		 *
		 * Because no columns have been set, we're setting up the $this->set array,
		 * before running thru it.
		 */
		$this->set = [[]];
		foreach ($this->set as $id => $row){
			# Set the row removed date value
			$this->set[$id]['removed'] = "NOW()";

			# Set the removed by (if set)
			$this->set[$id]['removed_by'] = $user_id ?: NULL;
			//at times, there may not be a given user performing the insert (user creation, etc)
		}

		# Add the columns to the list of columns that will be populated by the insert
		$this->addColumns(["removed", "removed_by"]);
	}
}