<?php

namespace App\Common\SQL\mySQL;

use App\Common\str;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class Export extends Common {
	private string $type = 'csv';
	private ?array $header = NULL;

	public function __construct(\mysqli $mysqli, $export)
	{
		parent::__construct($mysqli);

		if(!is_array($export)){
			return;
		}

		$this->type = $export['type'] ?: $this->type;
		$this->header = is_array($export['header']) ? $export['header'] : NULL;
	}

	public function export($query): string
	{
		return $this->{$this->type}($query);
	}

	/**
	 * Assumes that PHPSpreadsheet has been installed.
	 * Takes the CSV and makes it into a XLSX file
	 *
	 * @param string $query
	 *
	 * @return string
	 * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
	 * @link https://stackoverflow.com/a/60542080/429071
	 */
	public function xlsx(string $query): string
	{
		$csv_file = $this->csv($query);
		$xlsx_file = $_ENV['tmp_dir'] . str::id("xlsx_output");

		$reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();

		/* Set CSV parsing options */
		$reader->setDelimiter(',');
		$reader->setEnclosure('"');
		$reader->setSheetIndex(0);

		/* Load a CSV file and save as a XLS */
		$spreadsheet = $reader->load($csv_file);
		$writer = new Xlsx($spreadsheet);
		$writer->save($xlsx_file);

		$spreadsheet->disconnectWorksheets();
		unset($spreadsheet);

		unset($csv_file);
		return $xlsx_file;
	}

	public function csv(string $query): string
	{
		$file = $_ENV['tmp_dir'] . str::id("csv_output");

		# Store the query in a session variable
		$_SESSION['query'] = $query;
		$_SESSION['queries'][] = str_replace(["\r\n"], " ", $query);
		$_SESSION['database_calls']++;

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

		# Run the query
		$result = $this->mysqli->query($query);

		# Open the file
		$fp = fopen($file, 'w');

		# If this is an empty array
		if(!$first_row = $result->fetch_assoc()){
			return $file;
		}

		# Write the header row
		if($this->header){
			// If an array of col => title has been provided, use it

			# Check to see if the header column has a title
			foreach(array_keys($first_row) as $key){
				$headers[] = $this->header[$key] ?: $key;
			}

			fputcsv($fp, $headers);
		}

		else {
			// If no header row is provided

			# Use the first row's keys
			fputcsv($fp, array_keys($first_row));
		}

		# Write the first row
		fputcsv($fp, $first_row);

		# Write all rows
		while($row = $result->fetch_assoc()) {
			fputcsv($fp, $row);
		}

		# Close the file
		fclose($fp);

		# Free up memory
		$result->close();

		# Return the filename
		return $file;
	}
}