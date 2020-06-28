<?php


namespace App\Common\SQL\mySQL;


class Update extends Common {
	public function update(array $a, ?bool $return_query = NULL)
	{
		extract($a);

		# Make sure there is a set to update
		if (!is_array($set)){
			throw new \Exception("An update request was sent with no columns to update.");
		}

		# Set the (main) table (needs to be done first)
		$this->setTable($db, $table, $id, $include_removed);

		# Grow the columns of the if they don't exist / are too small for the data
		if ($grow){
			$sql = new Grow($this->mysqli);
			$sql->growTable($this->table, $set);
		}

		# Set joins
		$this->setJoins("INNER", $join);
		$this->setJoins("LEFT", $left_join);

		# Set where
		$this->setWhere($where, $or);

		# Make sure the column or columns are identified
		if (!$id && !$this->whereConditionsExist()){
			throw new \Exception("An update request was sent with no WHERE clause or column ID to identify what to update.");
		}

		# Set limits
		$this->setLimit($limit, $start, $length);

		# If the user_id has not been set (to either a particular user ID or to be ignored)
		if (!array_key_exists("user_id", $a)){
			global $user_id;
			if (!$user_id){
				throw new \Exception("Updating without a user ID is not allowed.");
			}
		}

		# Set the columns that are going to be SET into this table
		$this->setSet($set, $html);

		# Add ID, created by and time
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
		 * With UPDATE queries, we will never have more than one row in the SET
		 * array, but we're sharing methods with the INSERT query, where
		 * multiple SETs may occur.
		 */
		foreach ($this->set as $id => $row){
			# Set the row updated date value
			$this->set[$id]['updated'] = "NOW()";

			# Set the updated by (if set)
			$this->set[$id]['updated_by'] = $user_id ?: NULL;
			//at times, there may not be a given user performing the insert (user creation, etc)
		}

		# Add the columns to the list of columns that will be populated by the insert
		$this->addColumns(["updated", "updated_by"]);
	}
}