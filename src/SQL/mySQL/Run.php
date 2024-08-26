<?php


namespace App\Common\SQL\mySQL;


use App\Common\Exception\MySqlException;
use App\Common\str;

class Run extends Common {
	/**
	 * The number of times to try to re-run a query
	 * if the connection is lost.
	 */
	const MAX_TRIES = 5;

	/**
	 * Runs a SQL query. Returns an array with the following
	 * keys:
	 * - `query` The SQL query just run
	 * - `rows` (SELECT only), the rows matching the query
	 * - `num_rows` (SELECT only) The number of rows found by the query
	 * - `affected_rows` The number of rows affected by the query
	 *
	 * @param string    $query
	 * @param bool|null $log If set to false, will not log the query in the session variable.
	 * @param int|null  $tries
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function run(string $query, ?bool $log = true, ?int $tries = 1): array
	{
		# Store the query in a session variable
		if($log){
			$this->logRun($query);
		}

		# Ensure the connection is live
		$this->ensureConnection();

		# Try
		try {

			# Multi-query
			if($this->isMultiQuery($query)){
				//If there is more than one query to run

				# Run the multi-query
				if(!$result = @$this->mysqli->multi_query($query)){
					// Something went wrong
					throw new MySqlException("SQL multi-query error: " . mysqli_connect_error(), mysqli_connect_errno());
				}

				# Consume the results so that a query can be run afterwards
				while($this->mysqli->next_result()) {
					//Otherwise threads will collide
				}
			}

			# Single query (most common)
			else {
				//if there is only one query to run

				# Run the query
				if(!$result = @$this->mysqli->query($query)){
					// Something went wrong
					throw new MySqlException("SQL query error: " . mysqli_connect_error(), mysqli_connect_errno());
				}
			}
		}

		# Catch either type of mySQL error
		catch(MySqlException | \mysqli_sql_exception $e) {
			# Grab the error message
			$message = $e->getMessage();
			// Grabbing it so that we can optionally add to it

			# Common error messages that warrant a re-run
			switch($message) {
			case 'MySQL server has gone away':
			case 'Deadlock found when trying to get lock; try restarting transaction':
//			default:
				# Try again if we haven't tried too many times
				if($tries <= self::MAX_TRIES){
					# Close the connection
					$this->mysqli->close();
					# Sleep
					sleep($tries);
					# Create a new connection
					$this->mysqli = mySQL::getNewConnection();
					# Count the try
					$tries++;
					# Rerun the query
					return $this->run($query, $log, $tries);
				}

				# Note to the log that we tried really hard to make this work
				$message .= ". Tried to re-run the query ".self::MAX_TRIES." times.";
				break;
			}

			throw new MySqlException($message, $e->getCode(), $e);
		}

		/**
		 * For successful SELECT, SHOW, DESCRIBE or EXPLAIN queries
		 * mysqli_query will return a mysqli_result object.
		 * For other successful queries mysqli_query will return
		 * true and false on failure.
		 */

		if(is_object($result)){
			# Store result rows in an array
			while($row = $result->fetch_assoc()) {
				$rows[] = $row;
			}

			$num_rows = $result->num_rows;

			# Free up memory
			$result->close();
		}

		else {
			$affected_rows = $this->mysqli->affected_rows;
		}

		return [
			"query" => $query,
			"rows" => $rows,
			"num_rows" => $num_rows,
			"affected_rows" => $affected_rows,
		];
	}

	/**
	 * Returns true if the query string is actually multiple queries.
	 *
	 * @param string $query
	 *
	 * @return void
	 * @link https://stackoverflow.com/a/1060703/429071
	 */
	private function isMultiQuery(string $query): bool
	{
		# We're only interested in semicolons if they appear outside quoted strings
		$filtered_query = preg_replace("/([\"'])(?:\\\\?+.)*?\\1/", '', $query);

		# The query must include at least one semicolon
		if(strpos($filtered_query, ";") === false){
			return false;
		}

		# The semicolon must divide the query into at least two parts
		$parts = array_filter(explode(";", trim($filtered_query)));
		return count($parts) > 1;
	}

	/**
	 * Sometimes the mysqli connection is lost.
	 * This method ensures that if the connection is lost,
	 * it is re-established.
	 *
	 * @return void
	 * @throws \Exception
	 */
	private function ensureConnection(): void
	{
		if(!$this->mysqli){
			$this->mysqli = mySQL::getNewConnection();
		}
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
			"query" => str_replace(["\r\n"], " ", $query),
			"backtrace" => str::backtrace(true, false),
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
			$SESSION['queries'][] = str_replace(["\r\n"], " ", $query);
			$SESSION['database_calls']++;
		}
	}
}