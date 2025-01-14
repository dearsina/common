<?php

namespace App\Common\SQL;

use App\Common\Exception\BadRequest;
use App\Common\str;
use Box\Spout\Reader\ReaderInterface;
use Box\Spout\Reader\SheetInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception;

class SheetHandler extends \App\Common\Prototype {
	/**
	 * The number of rows to import at a time.
	 */
	const CHUNK = 1000;

	/**
	 * The maximum number of columns that are accepted
	 * by the importer.
	 */
	const MAX_NUMBER_OF_COLUMNS = 200;

	/**
	 * The spreadsheet file reader.
	 *
	 * @var ReaderInterface
	 */
	private ReaderInterface $reader;

	/**
	 * Contains the row and col count of
	 * the given sheet.
	 *
	 * @var array
	 */
	private array $sheet_metadata = [];

	private ?string $meta_db;
	private ?string $meta_table;
	private ?string $meta_id;
	private ?string $data_db;
	private ?string $data_table;

	private array $meta_table_columns = [];
	private array $data_table_columns = [];

	/**
	 * The number of rows that have been imported
	 * for a given sheet.
	 *
	 * @var int
	 */
	private array $inserted_sheet_rows_count = [];

	/**
	 * If set, will update the progress bar and
	 * send the value to the given container.
	 *
	 * @var string|null
	 */
	private ?string $progress_bar_container = NULL;

	public function __construct(array $file, ?string $progress_bar_container = NULL, ?string $meta_db = NULL, ?string $meta_table = NULL, ?string $meta_id = NULL, ?string $data_db = NULL, ?string $data_table = NULL)
	{
		parent::__construct();

		$this->file = $file;

		# Create reader that matches the file name
		$this->reader = \Box\Spout\Reader\Common\Creator\ReaderEntityFactory::createReaderFromFile($file['name']);

		# Open the file
		$this->reader->open($file['tmp_name']);

		# Set the sheet metadata
		$this->setSheetMetadata();

		# Set the meta table details
		$this->meta_db = $meta_db;
		$this->meta_table = $meta_table;
		$this->meta_id = $meta_id;

		# Set the data table details
		$this->data_db = $data_db;
		$this->data_table = $data_table ?: $this->meta_id;

		# Set the progress bar container
		$this->progress_bar_container = $progress_bar_container;
	}

	/**
	 * @return string|null
	 */
	public function getMetaId(): ?string
	{
		return $this->meta_id;
	}

	/**
	 * Returns an array of the sheet names in the file.
	 *
	 * @return array
	 * @throws \Box\Spout\Reader\Exception\ReaderNotOpenedException
	 */
	public function getSheetNames(): array
	{
		$sheet_names = [];
		foreach($this->reader->getSheetIterator() as $sheet){
			$sheet_names[] = $sheet->getName();
		}
		return $sheet_names;
	}

	/**
	 * Returns an array of the sheet metadata:
	 * - row_count
	 * - col_count
	 *
	 * The key is the sheet name.
	 *
	 * @return array
	 */
	public function getSheetMetadata(): array
	{
		return $this->sheet_metadata;
	}

	/**
	 * Sets the sheet metadata:
	 * - row_count
	 * - col_count
	 *
	 * The key is the sheet name.
	 *
	 * @return array|null
	 * @throws Exception
	 */
	private function setSheetMetadata(): void
	{
		$reader = IOFactory::createReader(ucfirst($this->file['ext']));
		$worksheets = $reader->listWorksheetInfo($this->file['tmp_name']);

		foreach($worksheets as $worksheet){
			# If the sheet has more than the max number of columns, we don't want to prep it for import
			if($worksheet['totalColumns'] > self::MAX_NUMBER_OF_COLUMNS){
				$this->log->warning([
					"title" => "Too many columns",
					"message" => "The {$sheet_name} sheet has {$col_count} columns. Sheets with more than " . self::MAX_NUMBER_OF_COLUMNS . " columns will not be imported.
					If the column count doesn't correspond with the number of columns that actually hold data, you may find it useful to copy
					and paste the data columns (only) into a new sheet, delete this sheet and start the import process again.",
				]);
				continue;
			}

			$this->sheet_metadata[$worksheet['worksheetName']] = [
				'row_count' => $worksheet['totalRows'],
				'col_count' => $worksheet['totalColumns'],
			];
		}
	}

	/**
	 * Add columns to the metadata table. The metadata
	 * table contains information about the table being
	 * imported like the sheet name, row count, etc.
	 *
	 * @param array $a
	 *
	 * @return void
	 */
	public function setMetaTableColumns(array $a): void
	{
		$this->meta_table_columns = $a;
	}

	/**
	 * Add use-case specific columns to the data table.
	 *
	 * @param array $a
	 *
	 * @return void
	 */
	public function setDataTableColumns(array $a): void
	{
		$this->data_table_columns = $a;
	}

	/**
	 * Given a sheet name, will import the given sheet,
	 * assuming it exists, and has rows. Otherwise, the
	 * method will return false.
	 *
	 * @param string|null $sheet_name
	 *
	 * @return bool
	 */
	public function importSheet(?string $sheet_name): bool
	{
		# Ensure the sheet exists
		if(!$sheet = $this->getSheet($sheet_name)){
			return false;
		}

		# Ensure the sheet has rows
		if($this->sheet_metadata[$sheet_name]['row_count'] == 0){
			return false;
		}

		# Ensure a metadata table has been set for the sheet
		$this->createMetadataTable($sheet_name);

		# Ensure the data table has been created for the sheet
		$this->createDataTable($sheet_name);

		# Start the clock
		$this->startTheClock();

		# Go through each row in the sheet
		foreach($sheet->getRowIterator() as $row){
			//foreach row

			# Get all the row columns as an array
			$columns = $row->toArray();

			# Add the whole row to the rows array
			$rows[] = $columns;

			# If we've collected enough row data, insert the rows
			if(count($rows) == self::CHUNK){
				// if the chunk is full

				# Insert the rows in bulk (will clear the rows afterwards)
				$this->insertDataRows($sheet_name, $rows);

				# Update the progress bar
				$this->updateSheetProgressBar($sheet_name);
			}
		}

		# All the remaining rows that don't quite make up a chunk
		if($rows){
			// If there are any straggler rows, insert them also
			$this->insertDataRows($sheet_name, $rows);
		}

		# Create the columns table
		$this->createColumnsTable($sheet_name, $sheet);

		# Identify columns with unique values
		SheetHandler::identifyUniqueColumns($this->meta_db, $this->meta_table, $this->meta_id, $this->data_db, $this->data_table);

		# Identify empty columns
		SheetHandler::identifyEmptyColumns($this->meta_db, $this->meta_table, $this->meta_id, $this->data_db, $this->data_table);

		# Identify duplicate rows
		SheetHandler::identifyDuplicateRows($this->meta_db, $this->meta_table, $this->meta_id, $this->data_db, $this->data_table);

		# And we're done, so set the progress bar to 100%
		$this->updateSheetProgressBar($sheet_name, true);

		return true;
	}

	/**
	 * Returns the number of seconds since the start.
	 *
	 * @param bool|null $return_his
	 *
	 * @return string
	 */
	private function readTheClock(): int
	{
		$now = microtime(true);
		return round($now - $this->script_start_time, 3);
	}

	/**
	 * Starts the clock.
	 */
	private function startTheClock(): void
	{
		$this->script_start_time = microtime(true);
	}

	private function updateSheetProgressBar(string $sheet_name, ?bool $force_hundred = NULL): void
	{
		# Only update the progress bar if a container was passed
		if(!$this->progress_bar_container){
			return;
		}

		if($force_hundred){
			//if set to NULL, it means there are no rows, automatic 100%
			$percent = 100;
			$post = "00:00:00";
		}

		else {
			//otherwise, calculate the progress made

			# Calculate the progress made
			if(!$this->sheet_metadata[$sheet_name]['row_count']){
				$fraction = 0;
			}
			else {
				$fraction = $this->inserted_sheet_rows_count[$sheet_name] / $this->sheet_metadata[$sheet_name]['row_count'];
			}

			# Calculate remaining seconds
			$rows_left = $this->sheet_metadata[$sheet_name]['row_count'] - $this->inserted_sheet_rows_count[$sheet_name];

			# Ensure the clock has started
			if(!$read_the_clock = $this->readTheClock()){
				return;
			}

			$rows_per_second = $this->inserted_sheet_rows_count[$sheet_name] / $read_the_clock;

			$seconds_remaining = (int)round($rows_left / $rows_per_second);

			# Prepare the post
			$post = str::getHisFromS($seconds_remaining);

			$percent = round(100 * ($fraction));
		}

		# Update the progress bar
		$this->output->function("Progress.updateProgressBar", [
			"container" => $this->progress_bar_container,
			"progress" => $percent,
			"post" => $post,
		], ["user_id" => $this->user->getId()]);
	}

	/**
	 * Given a cluster of data rows, insert them into the data table.
	 * The data table is the table that contains the actual data being
	 * imported.
	 *
	 * @param string $sheet_name
	 * @param array  $rows
	 *
	 * @return void
	 */
	private function insertDataRows(string $sheet_name, array $rows): void
	{
		# Last minute clean-up of the rows
		foreach($rows as $row){
			# Start with a clean set
			$set = [];

			foreach($row as $key => $val){
				# Ensure key is in the Excel style
				$key = !is_int($key) ? $key : str::excelKey($key);

				# Ensure Datetime objects are converted to strings
				if($val instanceof \DateTime){
					$val = $val->format("Y-m-d H:i:s");
				}

				# Trim whitespace from the value
				$val = str::trim($val);

				$set[$key] = $val;
			}

			# Move the set to the sets
			$sets[] = $set;
		}

		$this->sql->insert([
			"db" => $this->data_db,
			"table" => $this->data_table,
			"set" => $sets,
			"html" => array_keys(reset($sets)),
			"include_meta" => false,
			"include_id" => true,
		]);

		$this->inserted_sheet_rows_count['$sheet_name'] += count($rows);
	}

	private function createDataTable(string $sheet_name): void
	{
		# Ensure table doesn't already exist
		if($this->sql->tableExists($this->data_db, $this->data_table)){
			// If the table already exists, drop it
			$this->sql->run("DROP TABLE `{$this->data_db}`.`{$this->data_table}`;");
		}

		# Set the table ID column
		$cols[] = "`{$this->data_table}_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL";

		# Add any use-case specific columns
		if($this->data_table_columns){
			$cols = array_merge($cols, $this->data_table_columns);
		}

		# Add the data columns using the Excel column names
		for($i = 0; $i < $this->sheet_metadata[$sheet_name]['col_count']; $i++){
			$cols[] = "`" . str::excelKey($i) . "` TEXT COLLATE utf8mb4_0900_ai_ci DEFAULT NULL";
		}

		# Add an index to the ID column
		$cols[] = "PRIMARY KEY (`{$this->data_table}_id`)";

		# Create the table query
		$query = "CREATE TABLE `{$this->data_db}`.`{$this->data_table}` (" . implode(",", $cols) . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;";
		// The name of the data table is the same as the meta table row ID

		# Execute the query to create the table
		$this->sql->run($query);
	}

	private function createColumnsTable(string $sheet_name, SheetInterface $sheet): void
	{
		# Ensure rows don't already exist
		if($form_list_cols = $this->sql->select([
			"db" => $this->meta_db,
			"table" => "{$this->meta_table}_col",
			"where" => [
				"{$this->meta_table}_id" => $this->meta_id,
			],
		])){
			if (count($form_list_cols) >= $this->sheet_metadata[$sheet_name]['col_count']){
				// And the number of columns matches the number of columns in the sheet
				$this->form_list_cols = $form_list_cols;
				return;
			}

			# If new columns have been added
			$i = $this->sheet_metadata[$sheet_name]['col_count'] - 1;
		}

		if($this->meta['header_row'] != NULL){
			foreach($sheet->getRowIterator() as $row_number => $row){
				if($row_number != $this->meta['header_row']){
					continue;
				}
				$headers = $row->toArray();
				break;
			}
		}

		# Otherwise, we need to create the table
		for($i = $i ?: 0; $i < $this->sheet_metadata[$sheet_name]['col_count']; $i++){
			$this->sql->insert([
				"db" => $this->meta_db,
				"table" => "{$this->meta_table}_col",
				"set" => [
					"subscription_id" => $this->meta['subscription_id'],
					"{$this->meta_table}_id" => $this->meta_id,
					"col" => str::excelKey($i),
					"title" => $headers[$i] ?: str::excelKey($i),
				],
			]);
		}
	}

	/**
	 * Will identify the unique columns in the data table,
	 * and mark them in the meta table.
	 *
	 * @param string|null $meta_db
	 * @param string|null $meta_table
	 * @param string|null $meta_id
	 * @param string|null $data_db
	 * @param string|null $data_table
	 *
	 * @return void
	 * @throws \App\Common\Exception\BadRequest
	 */
	public static function identifyUniqueColumns(?string $meta_db = NULL, ?string $meta_table = NULL, ?string $meta_id = NULL, ?string $data_db = NULL, ?string $data_table = NULL): void
	{
		$sql = Factory::getInstance();

		$data_table_columns = $sql->getTableMetadata([
			"db" => $data_db,
			"name" => $data_table,
		]);

		$metadata = $sql->select([
			"columns" => [
				"header_row",
				"row_count",
			],
			"db" => $meta_db,
			"table" => $meta_table,
			"id" => $meta_id
		]);

		foreach($data_table_columns as $column => $column_data){
			$result = $sql->select([
				"cte" => [
					"limited" => [
						"db" => $data_db,
						"table" => $data_table,
						"columns" => $column,
						"where" => [
							"{$column} IS NOT NULL",
						],
						"include_removed" => true,
						"limit" => [$metadata['header_row'], $metadata['row_count'] - $metadata['header_row']]
					]
				],
				"table" => "limited",
				"columns" => [
					"indistinct" => ["COUNT(`{$column}`)"],
					"distinct" => ["COUNT(DISTINCT `{$column}`)"],
				],
				"group_by" => "`{$column}`",
				/**
				 * Because the columns are not explicitly defined as aggregates,
				 * we need to group by the column manually to avoid a mySQL error.
				 */
				"limit" => 1
			]);

			$sql->update([
				"db" => $meta_db,
				"table" => "{$meta_table}_col",
				"where" => [
					"{$meta_table}_id" => $meta_id,
					"col" => $column,
				],
				"set" => [
					"is_unique" => $result['indistinct'] && ($result['indistinct'] == $result['distinct']) ? 1 : NULL,
				],
			]);
		}
	}

	public static function updateRowCount(?string $meta_db = NULL, ?string $meta_table = NULL, ?string $meta_id = NULL, ?string $data_db = NULL, ?string $data_table = NULL): void
	{
		$sql = Factory::getInstance();

		$row_count = $sql->select([
			"count" => true,
			"db" => $data_db,
			"table" => $data_table,
			"include_removed" => true,
		]);

		$sql->update([
			"db" => $meta_db,
			"table" => $meta_table,
			"where" => [
				"{$meta_table}_id" => $meta_id,
			],
			"set" => [
				"row_count" => $row_count
			],
		]);
	}

	public static function updateColCount(?string $meta_db = NULL, ?string $meta_table = NULL, ?string $meta_id = NULL, ?string $data_db = NULL, ?string $data_table = NULL): void
	{
		$sql = Factory::getInstance();

		$data_table_columns = $sql->getTableMetadata([
			"db" => $data_db,
			"name" => $data_table,
		]);

		$sql->update([
			"db" => $meta_db,
			"table" => $meta_table,
			"where" => [
				"{$meta_table}_id" => $meta_id,
			],
			"set" => [
				"col_count" => count($data_table_columns)
			],
		]);
	}
	
	public static function refreshMetaData(?string $meta_db = NULL, ?string $meta_table = NULL, ?string $meta_id = NULL, ?string $data_db = NULL, ?string $data_table = NULL): void
	{
		# Identify unique columns
		SheetHandler::identifyUniqueColumns($meta_db, $meta_table, $meta_id, $data_db, $data_table);

		# Identify empty columns
		SheetHandler::identifyEmptyColumns($meta_db, $meta_table, $meta_id, $data_db, $data_table);

		# Identify the row count
		SheetHandler::updateRowCount($meta_db, $meta_table, $meta_id, $data_db, $data_table);

		# Identify the col count
		SheetHandler::updateColCount($meta_db, $meta_table, $meta_id, $data_db, $data_table);
	}

	/**
	 * Will mark any column with no values
	 * in the data table. Takes into consideration
	 * the header row position.
	 *
	 * @param string|null $meta_db
	 * @param string|null $meta_table
	 * @param string|null $meta_id
	 * @param string|null $data_db
	 * @param string|null $data_table
	 *
	 * @return void Returns an array of the column names (letters) that were removed, if any.
	 * @throws BadRequest
	 */
	public static function identifyEmptyColumns(?string $meta_db = NULL, ?string $meta_table = NULL, ?string $meta_id = NULL, ?string $data_db = NULL, ?string $data_table = NULL): void
	{
		$sql = Factory::getInstance();

		$meta_data = $sql->select([
			"db" => $meta_db,
			"table" => $meta_table,
			"id" => $meta_id,
		]);

		$data_table_columns = $sql->getTableMetadata([
			"db" => $data_db,
			"name" => $data_table,
		]);

		foreach($data_table_columns as $column => $column_data){
			$sql->update([
				"db" => $meta_db,
				"table" => "{$meta_table}_col",
				"where" => [
					"{$meta_table}_id" => $meta_id,
					"col" => $column,
				],
				"set" => [
					# If no data can be found in the column, it's empty
					"is_empty" => !$sql->select([
						"cte" => [
							"limited" => [
								"db" => $data_db,
								"table" => $data_table,
								"columns" => $column,
								"include_removed" => true,
								"limit" => [$meta_data['header_row'], $meta_data['row_count'] - $meta_data['header_row']]
							]
						],
						"table" => "limited",
						"where" => [
							"{$column} IS NOT NULL",
						],
						"limit" => 1
					])
					/**
					 * In this context, data means a value that is not NULL.
					 * We also take into consideration where the data starts,
					 * which is defined by the header row.
					 */
				],
			]);
		}
	}

	/**
	 * Identify duplicate rows in a data table,
	 * and write the results to the meta table.
	 *
	 * Will return the number of duplicate rows found.
	 * That number could be 0.
	 *
	 * @param string|null $meta_db
	 * @param string|null $meta_table
	 * @param string|null $meta_id
	 * @param string|null $data_db
	 * @param string|null $data_table
	 *
	 * @return int
	 * @throws \App\Common\Exception\BadRequest
	 */
	public static function identifyDuplicateRows(?string $meta_db = NULL, ?string $meta_table = NULL, ?string $meta_id = NULL, ?string $data_db = NULL, ?string $data_table = NULL): int
	{
		$sql = Factory::getInstance();

		# Get the columns in the data table as a comma separated string
		$columns = implode(",", array_keys($sql->getTableMetadata([
			"db" => $data_db,
			"name" => $data_table,
		])));

		# Write the query to find duplicate rows
		$query = "SELECT {$columns}, COUNT(*) FROM `{$data_db}`.`{$data_table}` GROUP BY {$columns} HAVING COUNT(*) > 1;";

		# Execute the query and get the number of duplicate rows
		$duplicate_rows_count = $sql->run($query)['num_rows'];

		# Update the meta table with the number of duplicate rows, if any
		$sql->update([
			"db" => $meta_db,
			"table" => $meta_table,
			"id" => $meta_id,
			"set" => [
				"duplicate_rows_count" => $duplicate_rows_count,
			],
		]);

		return $duplicate_rows_count;
	}

	public static function deleteDuplicateRows(?string $meta_db = NULL, ?string $meta_table = NULL, ?string $meta_id = NULL, ?string $data_db = NULL, ?string $data_table = NULL): ?int
	{
		$sql = Factory::getInstance();

		# Get the columns in the data table as a comma separated string
		$columns = array_keys($sql->getTableMetadata([
			"db" => $data_db,
			"name" => $data_table,
		]));

		foreach($columns as $column){
			$and_column_comparisons .= "AND (\r\n\t(t1.`{$column}` IS NULL AND t2.`{$column}` IS NULL)\r\n\tOR t1.`{$column}` = t2.`{$column}`\r\n)\r\n";
		}

		# Write the query to delete any duplicate rows from the table where only the ID column is definitely unique
		$query = "
		DELETE t1
		FROM `$data_db`.`$data_table` t1
		JOIN `$data_db`.`$data_table` t2
		WHERE t1.`{$data_table}_id` < t2.`{$data_table}_id`
		AND t1.`{$data_table}_id` <> ''
		{$and_column_comparisons}
		";

		# Run the delete query
		$result = $sql->run($query);

		# Update the row count in the meta table
		if($result['affected_rows']){
			// But only if rows were removed
			$sql->update([
				"db" => $meta_db,
				"table" => $meta_table,
				"id" => $meta_id,
				"set" => [
					"row_count" => $sql->select([
						"db" => $data_db,
						"table" => $data_table,
						"count" => true,
						"include_removed" => true,
					]),
				],
			]);
		}

		# Identify the duplicate rows again, should be zero
		SheetHandler::identifyDuplicateRows($meta_db, $meta_table, $meta_id, $data_db, $data_table);

		# Identify any potential unique columns now that duplicates have been removed
		SheetHandler::identifyUniqueColumns($meta_db, $meta_table, $meta_id, $data_db, $data_table);

		return $result['affected_rows'];
	}

	/**
	 * Generate a unique ID for the relationship between
	 * the row in the metadata table and the data table.
	 *
	 * Will also load the meta array.
	 *
	 * @param string $sheet_name
	 *
	 * @return void
	 * @throws \Exception
	 */
	private function createMetadataTable(string $sheet_name): void
	{
		$set['file_name'] = $this->file['name'];
		$set['file_md5'] = md5_file($this->file['tmp_name']);
		$set['sheet_name'] = $sheet_name;
		$set['row_count'] = $this->sheet_metadata[$sheet_name]['row_count'];
		$set['col_count'] = $this->sheet_metadata[$sheet_name]['col_count'];

		$set['title'] = $sheet_name;
		$set['desc'] = $set['file_name'];

		if($this->meta_id){
			$this->meta = $this->info([
				"db" => $this->meta_db,
				"rel_table" => $this->meta_table,
				"rel_id" => $this->meta_id,
			]);

			# Update some metadata with the new file
			$this->sql->update([
				"db" => $this->meta_db,
				"table" => $this->meta_table,
				"id" => $this->meta_id,
				"set" => $set
			]);

			return;
		}

		# If there are custom meta table columns to add, add them here
		if($this->meta_table_columns){
			$set = array_merge($set, $this->meta_table_columns);
		}

		# Create a new metadata row
		$this->meta_id = $this->sql->insert([
			"db" => $this->meta_db,
			"table" => $this->meta_table,
			"set" => $set,
		]);

		# The data table is named after the metadata ID for easy reference
		$this->data_table = $this->meta_id;

		$this->meta = $this->info([
			"db" => $this->meta_db,
			"rel_table" => $this->meta_table,
			"rel_id" => $this->meta_id,
		]);
	}

	private function getSheet(string $sheet_name): ?\Box\Spout\Reader\SheetInterface
	{
		foreach($this->reader->getSheetIterator() as $sheet){
			if($sheet_name == $sheet->getName()){
				return $sheet;
			}

			# Make an exception for CSV files where the sheet name can be blank
			if($sheet_name == "Worksheet" && $sheet->getIndex() == 0 && $sheet->getName() == ""){
				return $sheet;
			}
		}

		return NULL;
	}
}