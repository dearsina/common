<?php


namespace App\Common\SQL\mySQL;


class Delete extends Common {
	public function delete(array $a, ?bool $return_query = NULL)
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
		$this->setTable($db, $table, $id, true);

		# Set joins
		$this->setJoins("INNER", $join);
		$this->setJoins("LEFT", $left_join);

		# Set where
		$this->setWhere($where, $or);

		# Set limits
		$this->setLimit($limit, $start, $length);

		# Make sure the column or columns are identified
		if (!$id && !$this->whereConditionsExist()){
			throw new \Exception("An delete request was sent with no WHERE clause or column ID to identify what to remove.");
		}

		$query = $this->generateDeleteSQL();

		# If only the SQL is requested
		if ($return_query){
			return $query;
		}

		# Execute the SQL
		$sql = new Run($this->mysqli);
		return $sql->run($query);
		// Return the results that will contain some metadata about the results
	}

	private function generateDeleteSQL(): string
	{
		$query[] = "DELETE `{$this->table['alias']}`";
		$query[] = $this->getTableSQL();
		$query[] = $this->getJoinsSQL();
		$query[] = $this->getWhereSQL();
		$query[] = $this->getLimitSQL();
		return implode("\r\n", array_filter($query));
	}
}