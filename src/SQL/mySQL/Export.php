<?php

namespace App\Common\SQL\mySQL;

use App\Common\str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class Export extends Common {
	/**
	 * Different mime types for the export.
	 */
	public const MIME_TYPES = [
		"xlsx" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
		"csv" => "text/csv"
	];

	/**
	 * Default format is CSV.
	 *
	 * @var string|mixed
	 */

	private string $format = 'csv';

	/**
	 * Custom header.
	 *
	 * @var array|mixed|null
	 */
	private ?array $header = NULL;

	/**
	 * Set up SQL result exports.
	 *
	 * @param \mysqli    $mysqli
	 * @param array|null $settings Optional array containing `format` (`csv` or `xlsx`) and `header`, an array containing
	 *                           column => titles
	 */
	public function __construct(?\mysqli $mysqli = NULL, ?array $settings)
	{
		if($mysqli){
			parent::__construct($mysqli);
		}

		if(!is_array($settings)){
			return;
		}

		$this->format = $settings['format'] ?: $this->format;
		$this->header = $settings['header'];
	}

	public function getFilename(?string $filename, ?bool $date_suffix = true, ?bool $time_suffix = true): string
	{
		if(!$filename){
			$filename = "export";
		}

		if($date_suffix || $time_suffix){
			$filename .= " [";
		}

		if($date_suffix){
			$filename .= date("Y-m-d");
		}

		if($date_suffix && $time_suffix){
			$filename .= " ";
		}

		if($time_suffix){
			$filename .= date("H-i-s");
		}

		if($date_suffix || $time_suffix){
			$filename .= "]";
		}

		switch($this->format){
			case 'csv':
				$filename .= ".csv";
				break;
			case 'xlsx':
				$filename .= ".xlsx";
				break;
		}

		return $filename;
	}

	/**
	 * Export SQL results, returning an array.
	 *
	 * @param $query
	 *
	 * @return string
	 */
	public function export(?string $query = NULL, ?array $rows = NULL): string
	{
		if($query){
			switch($this->format){
				case 'csv':
					return $this->generateCsvFromQuery($query);
				case 'xlsx':
					return $this->generateXlsxFromQuery($query);
			}
		}

		else if($rows){
			switch($this->format){
			case 'csv':
				return $this->generateCsvFromRows($rows);
			case 'xlsx':
				return $this->generateXlsxFromRows($rows);
			}

		}

		else {
			throw new \Exception("No query or rows provided to export.");
		}
	}

	public function deleteTmpFile(): void
	{
		unlink($this->tmp_file);
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
	private function generateXlsxFromQuery(string $query): string
	{
		$csv_file = $this->generateCsvFromQuery($query);
		return $this->generateXlsxFromCsv($csv_file);
	}

	private function generateXlsxFromRows(array $rows): string
	{
		$csv_file = $this->generateCsvFromRows($rows);
		return $this->generateXlsxFromCsv($csv_file);
	}

	private function generateXlsxFromCsv(string $csv_file): string
	{
		$xlsx_file = $_ENV['tmp_dir'] . str::id("xlsx_output");

		# Create a new Spreadsheet object
		$spreadsheet = new Spreadsheet();
		$worksheet = $spreadsheet->getActiveSheet();

		# Open the CSV file and manually loop through it to ensure every cell is a string
		if (($handle = fopen($csv_file, "r")) !== FALSE) {
			$row = 1;
			# Read the CSV file line by line
			while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
				$col = 'A';
				# For each field in the CSV row, insert it into the spreadsheet as a string
				foreach ($data as $field) {
					$worksheet->setCellValueExplicit($col . $row, $field, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
					# Increment column letter
					$col++;
				}
				# Increment row number
				$row++;
			}
			fclose($handle);
		}
		// We do this to avoid the scientific notation of large numbers

		$writer = new Xlsx($spreadsheet);
		$writer->getSpreadsheet()->getActiveSheet()->getStyle('A1:Z99')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
		$writer->save($xlsx_file);

		$spreadsheet->disconnectWorksheets();
		unset($spreadsheet);

		unset($csv_file);
		$this->tmp_file = $xlsx_file;
		return $xlsx_file;
	}

	private function getTmpCsvFileName(): string
	{
		return $_ENV['tmp_dir'] . str::id("csv_output");
	}

	private function generateCsvFromQuery(string $query): string
	{
		$csv_file = $this->getTmpCsvFileName();

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
		$fp = fopen($csv_file, 'w');

		# If this is an empty array
		if(!$first_row = $result->fetch_assoc()){
			return $csv_file;
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
		$this->tmp_file = $csv_file;
		return $csv_file;
	}

	private function generateCsvFromRows(array $rows, ?array $header_row = NULL): string
	{
		$csv_file = $this->getTmpCsvFileName();

		if(!$header_row){
			if($this->header){
				$header_row = $this->header;
			}
			else {
				$header_row = array_keys($rows[0]);
			}
		}

		# Open the file
		$fp = fopen($csv_file, 'w');

		# Write the header row
		fputcsv($fp, $header_row);

		# Write all rows
		foreach($rows as $row) {
			fputcsv($fp, $row);
		}

		# Close the file
		fclose($fp);

		# Return the filename
		$this->tmp_file = $csv_file;
		return $csv_file;
	}
}