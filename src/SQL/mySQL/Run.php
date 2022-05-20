<?php


namespace App\Common\SQL\mySQL;


use App\Common\str;

class Run extends Common {
	/**
	 * Runs a SQL query.
	 *
	 * @param string $query
	 *
	 * @return array
	 */
	public function run(string $query, ?int $tries = 0): array
	{
		# Store the query in a session variable
		$this->logRun($query);

		$filtered_query = preg_replace("/([\"'])(?:\\\\?+.)*?\\1/", '', $query);
		// We're only interested in semicolons if they appear outside quoted strings
		// @link https://stackoverflow.com/a/1060703/429071

		if(strpos($filtered_query, ";") && count(array_filter(explode(";", trim($filtered_query)))) > 1){
			//If there is more than one query to run

			# Run the multi-query
			$result = $this->mysqli->multi_query($query);

			# Consume the results so that a query can be run afterwards
			while($this->mysqli->next_result()){
				//Otherwise threads will collide
			}
		}

		else {
			//if there is only one query to run

			try {
				# Run the query
				$result = $this->mysqli->query($query);
			}

			catch(\mysqli_sql_exception $e){
				# Grab the error message
				$message = $e->getMessage();
				// Grabbing it so that we can optionally add to it

				switch($message){
				case 'MySQL server has gone away':
					# Try again
					if($tries < 4){
						# Close the connection
						$this->mysqli->close();
						# Sleep
						sleep($tries);
						# Create a new connection
						$this->mysqli = mySQL::getNewConnection();
						# Count the try
						$tries++;
						# Rerun the query
						return $this->run($query, $tries);
					}
					# Note to the log that we tried really hard to make this work
					$message .= ". Tried to re-run the query {$tries} times.";
					break;
				}

				throw new \mysqli_sql_exception($message, $e->getCode(), $e);
			}
		}

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

	/**
	 * Logs executions of SQL queries,
	 * but only on the dev environment.
	 *
	 * @param string $query
	 */
	private function logRun(string $query): void
	{
		# We only want to log runs on the dev environment
		if(!str::isDev()){
			return;
		}

		$_SESSION['query'] = $query;
		$_SESSION['queries'][] = [
			"query_md5" => md5($query),
			"query" => str_replace(["\r\n"]," ",$query),
			"backtrace" => str::backtrace(true)
		];
		$_SESSION['database_calls']++;
		$_SESSION['query_timer'] = str::startTimer();

		if(str::runFromCLI()){
			/**
			 * When run from the command line, the session super global doesn't work,
			 * so instead we use the not so super global $SESSION instead.
			 * It works.
			 */
			global $SESSION;
			$SESSION['query'] = $query;
			$SESSION['queries'][] = str_replace(["\r\n"]," ",$query);
			$SESSION['database_calls']++;
		}
	}
}