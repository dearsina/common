<?php


namespace App\Common\SQL\mySQL;


use App\Common\str;

class Run extends Common {
	public function run(string $query): array
	{
		# Store the query in a session variable
		$_SESSION['query'] = $query;

		if(str::runFromCLI()){
			/**
			 * When run from the command line, the session super global doesn't work,
			 * so instead we use the not so super global $SESSION instead.
			 * It works.
			 */
			global $SESSION;
			$SESSION['query'] = $query;
			$SESSION['queries'][] = str_replace(["\r\n"]," ",$query);
		}

		# Run the query
		$result = $this->mysqli->query($query);

		/**
		 * For successful SELECT, SHOW, DESCRIBE or EXPLAIN queries
		 * mysqli_query will return a mysqli_result object.
		 * For other successful queries mysqli_query will return
		 * true and false on failure.
		 */

		if(is_object($result)){
			# Store result rows in an array
			while ($row = $result->fetch_assoc()) {
				$rows[] = $row;
			}

			$num_rows = $result->num_rows;

			# Free up memory
			$result->close();
		} else {
			$affected_rows = $this->mysqli->affected_rows;
		}

		return [
			"query" => $query,
			"rows" => $rows,
			"num_rows" => $num_rows,
			"affected_rows" => $affected_rows
		];
	}
}