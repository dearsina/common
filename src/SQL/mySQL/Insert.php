<?php


namespace App\Common\SQL\mySQL;


use App\Common\str;
use mysqli_sql_exception;
use Ramsey\Uuid\Codec\TimestampFirstCombCodec;
use Ramsey\Uuid\Generator\CombGenerator;
use Ramsey\Uuid\UuidFactory;

class Insert extends Common {
	/**
	 * Generate, execute a SQL insert query, inserting one or many rows
	 * of data into a single table.
	 *
	 * @param array     $a Insert data
	 * @param bool|null $return_query If set, will return the SQL query only.
	 *
	 * @return string|null Returns the FIRST ID only.
	 */
	public function insert(array $a, ?bool $return_query = NULL): ?string
	{
		extract($a);

		# Set the (main) table (needs to be done first)
		$this->setTable($db, $table);

		# Grow the columns of the if they don't exist / are too small for the data
		if($grow){
			$sql = new Grow($this->mysqli);
			$sql->growTable($this->table, $set);
		}

		# Set the columns that are going to be SET into this table
		$this->setSet($set, $html, $ignore_empty, $include_meta);

		# Add ID, created by and time
		if($include_meta !== false){
			$this->addRowMetadata($user_id);
		}

		# Alternatively, add ID only
		else if($include_id){
			$this->addRowID();
		}

		# Generate the query
		$query = $this->generateQuery();

		# If only the SQL is requested
		if($return_query){
			return $query;
		}

		# Execute the SQL
		$sql = new Run($this->mysqli);
		$sql->run($query, $log);

		# Returns the FIRST id (only)
		return reset($this->set)[$this->table["id_col"]];
	}
	/**
	 * Build a standard INSERT query.
	 *
	 * @return string
	 */
	private function generateQuery(): string
	{
		$query[] = "INSERT INTO `{$this->table['db']}`.`{$this->table['name']}`";
		$query[] = $this->getColumnsSQL();
		$query[] = $this->getValuesSQL();
		return implode("\r\n", array_filter($query));
	}

	/**
	 * Returns the column row in a SQL insert query.
	 *
	 * @return string
	 */
	private function getColumnsSQL():string
	{
		sort($this->columns);
		return "(`".implode("`, `", $this->columns)."`)";
	}

	/**
	 * Returns all the rows to be inserted, formatted.
	 *
	 * @return string
	 */
	private function getValuesSQL(): string
	{
		sort($this->columns);
		foreach($this->set as $row){
			$values = [];
			foreach($this->columns as $col){
				# Grab the expected column from the row array
				$val = $row[$col];
				//Not all columns are necessarily populated on each row

				# Set the formatted value
				$values[] = $this->formatInsertVal($this->table, $col, $val);
			}
			$rows[] = "(".implode(", ", $values).")";
		}
		return "VALUES\r\n".implode(",\r\n",$rows);
	}

	/**
	 * For each row to insert, add the metadata (ID, created by/when)
	 *
	 * @param string|null $user_id
	 */
	protected function addRowMetadata(?string $user_id = NULL): void
	{
		if(!$user_id){
			global $user_id;
		}

		if(empty($this->set)){
			$this->set = [[]];
		}

		foreach($this->set as $id => $row){
			# Generate a unique table row ID
			$this->set[$id][$this->table["id_col"]] = $this->generateUUID();

			# Set the row created date value
			$this->set[$id]['created'] = "NOW()";

			# Set the created by (if set)
			$this->set[$id]['created_by'] = $user_id ?: NULL;
			//at times, there may not be a given user performing the insert (user creation, etc)
		}

		# Add the columns to the list of columns that will be populated by the insert
		$this->addColumns([$this->table["id_col"],"created","created_by"]);
	}

	/**
	 * For each row to insert, add the ID (only).
	 *
	 * @param string|null $user_id
	 *
	 * @return void
	 */
	protected function addRowId(): void
	{
		if(empty($this->set)){
			$this->set = [[]];
		}

		foreach($this->set as $id => $row){
			# Generate a unique table row ID
			$this->set[$id][$this->table["id_col"]] = $this->generateUUID();
		}

		# Add the columns to the list of columns that will be populated by the insert
		$this->addColumns([$this->table["id_col"]]);
	}

	/**
	 * Generate a UUID
	 *
	 * This is different from the random UUID generator `str::uuid()`.
	 *
	 * UUID stands for Universally Unique IDentifier and is defined in the `RFC 4122`.
	 * It is a 128 bits number, normally written in hexadecimal and split by dashes
	 * into five groups. A typical UUID value looks like:
	 *
	 * <code>
	 * 905b194e-b7ab-42c2-af21-7dbd33e227e3
	 * </code>
	 *
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
	private function generateUUID(){
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
}