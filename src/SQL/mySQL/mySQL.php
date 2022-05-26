<?php

namespace App\Common\SQL\mySQL;

use App\Common\str;

use mysqli_sql_exception;
use Ramsey\Uuid\Codec\TimestampFirstCombCodec;
use Ramsey\Uuid\Generator\CombGenerator;
use Ramsey\Uuid\UuidFactory;
use Swoole\IDEHelper\Exception;
use Swoole\IDEHelper\StubGenerators\Swoole;

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
class mySQL extends Common {
	/**
	 * @var \mysqli
	 */
	protected \mysqli $mysqli;

	/**
	 * @var mySQL
	 */
	private static $instance;

	/**
	 * Connects to the SQL server, runs settings queries
	 * The constructor is private so that the class can be run in static mode
	 *
	 */
	public function __construct()
	{
		$driver = new \mysqli_driver();
		$driver->report_mode = MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR;
		$this->mysqli = self::getNewConnection();
	}

	public static function getNewConnection(?int $retry = 0): \mysqli
	{
		try {
			# Connect to the mySQL server
			$mysqli = new \mysqli($_ENV['db_servername'], $_ENV['db_username'], $_ENV['db_password'], $_ENV['db_database']);

			# Ensure everything is UTF8mb4
			$mysqli->set_charset('utf8mb4');

			# Ensure PHP and mySQL time zones are in sync
			$offset = (new \DateTime())->format("P");
			$mysqli->query("SET time_zone='$offset';");
		}

		catch(\mysqli_sql_exception $e) {
			if($e->getCode() == "2002"){
				//if it's a connection error, retry 3 times
				if($retry <= 3){
					$retry++;
					# Wait 3, 6, 9 seconds between tries
					sleep($retry * 3);
					return self::getNewConnection($retry);
				}
			}

			/**
			 * If there is an error connecting,
			 * put together a custom response,
			 * and stop everything, because
			 * without a database connection,
			 * nothing can be done.
			 */
			$message = "New SQL connection error [{$e->getCode()}]: {$e->getMessage()}. Retried {$retry} times.";

			# There is no point in continuing
			if(str::runFromCLI()){
				# Will somehow throw an error saying "MySQL server has gone away"
				throw new \Swoole\ExitException($message);
			}
			else {
				throw new \Exception($message, $e->getCode());
			}
		}

		return $mysqli;
	}

	private function __clone()
	{
		// Prevent the cloning of the object
	}

	private function __wakeup()
	{
		// Stopping unserializing of object
	}

	/**
	 * @return mySQL
	 */
	public static function getInstance()
	{
		// Check if instance is already exists
		if(self::$instance == NULL){
			self::$instance = new mySQL();
		}

		return self::$instance;
	}

	/**
	 * Run a SQL SELECT statement.
	 * Expects either a query array or string.
	 *
	 * <code>
	 * $this->sql->select([
	 * 	"db" => "",
	 * 	"table" => "",
	 * 	"export" => [
	 * 		"format" => $vars['format'],
	 * 		"header" => $dashboard_tile['form_field_ids'],
	 * 	],
	 * 	"include_removed" => true,
	 * ]);
	 * </code>
	 *
	 * @param array|string $a
	 * @param bool|null    $return_query
	 *
	 * @return array|bool|string
	 */
	public function select($a, ?bool $return_query = NULL)
	{
		if(is_string($a)){
			//If the select method variable is just a SQL string
			$sql = new Run($this->mysqli);
			$results = $sql->run($a);
			return $results['rows'];
		}

		if(!is_array($a)){
			//If it's not an array
			throw new mysqli_sql_exception("SQL select calls must be either in array or string format.");
		}

		# Reconnect (for long running scripts)
		if($a['reconnect']){
			$this->reconnect();
		}

		# Generate SQL, run query, return results
		$sql = new Select($this->mysqli);
		return $sql->select($a, $return_query);
	}

	/**
	 * <code>
	 * $rel_id = $this->sql->insert([
	 *    "table" => "",
	 *    "db" => "",
	 *    "set" => $set,
	 *    "user_id" => false,
	 *    "ignore_empty" => true
	 * ]);
	 * </code>
	 *
	 * @param array     $a
	 * @param bool|null $return_query
	 *
	 * @return mixed
	 */
	public function insert(array $a, ?bool $return_query = NULL)
	{
		return $this->call($a, $return_query, __FUNCTION__);
	}

	public function update(array $a, ?bool $return_query = NULL)
	{
		return $this->call($a, $return_query, __FUNCTION__);
	}

	public function remove(array $a, ?bool $return_query = NULL)
	{
		return $this->call($a, $return_query, __FUNCTION__);
	}

	public function restore(array $a, ?bool $return_query = NULL)
	{
		return $this->call($a, $return_query, __FUNCTION__);
	}

	public function delete(array $a, ?bool $return_query = NULL)
	{
		return $this->call($a, $return_query, __FUNCTION__);
	}

	private function call(array $a, ?bool $return_query, $call)
	{
		if(!is_array($a)){
			throw new mysqli_sql_exception("SQL {$call} calls must be in an array format.");
		}

		# Reconnect (for long running scripts)
		if($a['reconnect']){
			$this->reconnect();
		}

		$class = __NAMESPACE__ . '\\' . ucfirst($call);

		# Generate SQL, run query, return results
		$sql = new $class($this->mysqli);
		return $sql->{$call}($a, $return_query);
	}

	/**
	 * Given a fully formatted SQL query, runs it and returns an array
	 * with the following keys:
	 *  - `query` The SQL query just run
	 *  - `rows` (SELECT only), the rows matching the query
	 *  - `num_rows` (SELECT only) The number of rows found by the query
	 *  - `affected_rows` The number of rows affected by the query
	 *
	 * @param $query
	 *
	 * @return array
	 */
	public function run($query): array
	{
		$sql = new Run($this->mysqli);
		return $sql->run($query);
	}

	/**
	 * Reconnects to a mySQL server.
	 * Can be run even if the connection is still active.
	 *
	 * This is only really useful for when long-running
	 * scripts that can stay dormant for extended periods
	 * of time want to reconnect to the mySQL server.
	 *
	 * @param int|null $tries Used to keep track of re-tries.
	 *
	 * @return bool
	 */
	protected function reconnect(?int $tries = 0): bool
	{
		try {
			if($this->mysqli->ping()){
				return true;
			}
			return true;
		}
		catch(mysqli_sql_exception $e) {
			if($tries < 4){
				# Close the connection
				$this->mysqli->close();
				# Sleep
				sleep($tries);
				# Create a new connection
				if(self::__construct()){
					return true;
				}
				# Count the try
				$tries++;
				# Rerun the reconnection
				return $this->reconnect($tries);
			}
		}
		return false;
	}

	/**
	 * Frees up memory as the "queries" session variable is cleared.
	 * The "queries" session variable stores every single query run.
	 *
	 * @return bool
	 */
	public function freeUpMemory()
	{
		unset($_SESSION['queries']);
		return true;
	}

	/**
	 * Disconnects from the mySQL server.
	 * @return bool
	 */
	public function disconnect()
	{
		if(@$this->mysqli->thread_id){
			//if a connection has been opened
			$this->mysqli->close();
		}
		return true;
	}
}