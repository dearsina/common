<?php

namespace App\Common\Country;

use API\ExchangeRates\ExchangeRates;
use App\Common\Prototype;
use App\Common\SQL\Factory;
use App\Common\SQL\Info\Info;
use App\Common\str;

class Country extends Prototype {
	public ?string $db = "address";

	/**
	 * Get an array of currency code options
	 * to use in a dropdown select.
	 *
	 * Ignores currencies that we don't have an exchange rate for
	 *
	 * @return array
	 */
	public static function getCurrencyCodeOptions(): array
	{
		$sql = Factory::getInstance();
		$exchange_rates = ExchangeRates::get();
		foreach($sql->select([
			"columns" => [
				"currency_code",
				"countries" => ["group_concat", [
					"distinct" => true,
					"columns" => "name",
					"order_by" => [
						"country" => "ASC",
					],
					"separator" => ", ",
				],
				],
			],
			"table" => "country",
			"order_by" => [
				"currency_code" => "ASC",
			],
		]) as $id => $country){
			if(!$exchange_rates[$country['currency_code']]){
				//if we don't have an exchange rate for the currency, ignore it
				continue;
			}
			$currency_code_options[$country['currency_code']] = "{$country['currency_code']}, used by {$country['countries']}";
		}

		return $currency_code_options;
	}

	public static function getUserCurrencyCode(): ?string
	{
		global $user_id;

		if(!$user_id){
			return NULL;
		}

		if(!$currency = Factory::getInstance()->select([
			"columns" => "ip",
			"table" => "connection",
			"join" => [[
				"columns" => false,
				"table" => "geolocation",
				"on" => "ip",
			], [
				"columns" => "currency_code",
				"table" => "country",
				"on" => [
					"country_code" => ["geolocation", "country_code"],
				],
			]],
			"where" => [
				"closed" => NULL,
				["opened", "IS NOT", NULL],
				["ip", "IS NOT", NULL],
				"user_id" => $user_id,
			],
			"order_by" => [
				"created" => "DESC"
			],
			"limit" => 1,
		])){
			return NULL;
		}

		return $currency['geolocation'][0]['country'][0]['currency_code'];
	}

	/**
	 * Returns an array with the alpha-2 country code as the key,
	 * and the English country name as the value.
	 *
	 * <code>
	 * foreach(Country::getAllCountries() as $country_code => $country_name){
	 *
	 * }
	 * </code>
	 *
	 * @return array
	 * @throws \Exception#
	 */
	public static function getAllCountries(): array
	{
		$info = Info::getInstance();

		foreach($info->getInfo("country") as $country){
			$country_options[$country['country_code']] = $country['name'];
		}

		return $country_options;
	}

	public static function getAllNationalities(): array
	{
		$info = Info::getInstance();

		foreach($info->getInfo("country") as $country){
			$country_options[$country['country_code']] = $country['nationality'];
		}

		return $country_options;
	}

	/**
	 * Given an ISO 3166 alpha 3 code, returns the alpha 2.
	 * If it doesn't find it, will just return the alpha 3
	 * value.
	 *
	 * @param string|null $iso3
	 *
	 * @return string|null
	 * @throws \Exception
	 */
	public static function convertISO3toISO2(?string $iso3): ?string
	{
		if(!$iso3){
			return $iso3;
		}
		$countries = Info::getInstance()->getInfo("country");
		if(($key = array_search($iso3, array_column($countries, 'iso_alpha-3'))) === false){
			$key = array_search($iso3, array_column($countries, 'alt_iso_alpha-3'));
		}

		if($key == false){
			return $iso3;
		}
		return $countries[$key]['country_code'];
	}

	public static function getNationalityFromISOAlpha2(?string $iso2): ?string
	{
		if(!$iso2){
			return $iso2;
		}

		$countries = Info::getInstance()->getInfo("country");
		if(($key = array_search($iso2, array_column($countries, 'country_code'))) === false){
			return $iso2;
		}

		return $countries[$key]['nationality'];
	}

	/**
	 * Given a nationality string (case-insensitive), will return
	 * the ISO-alpha2 code of the country.
	 *
	 * Returns the nationality string if no match is found.
	 *
	 * @param string|null $nationality
	 *
	 * @return string|null
	 * @throws \Exception
	 */
	public static function getISOAlpha2FromNationality(?string $nationality): ?string
	{
		if(!$nationality){
			return $nationality;
		}

		# Make the string lowercase
		$nationality = strtolower($nationality);

		# Get all the countries
		$countries = Info::getInstance()->getInfo("country");

		# Run thru each country
		foreach($countries as $country){

			# Get all nationalities (some countries have more than one)
			$nationalities = explode(", ", strtolower($country['nationality']));

			# If it's one of them, return the country code
			if(in_array($nationality, $nationalities)){
				return $country['country_code'];
			}
		}

		return $nationality;
	}

	/**
	 * Given a nationality string (case-insensitive), will return
	 * the ISO-alpha2 code of the country.
	 *
	 * Returns the nationality string if no match is found.
	 *
	 * @param string|null $name
	 *
	 * @return string|null
	 * @throws \Exception
	 */
	public static function getISOAlpha2FromCountry(?string $name): ?string
	{
		if(!$name){
			return $name;
		}

		# Make the string lowercase
		$name = strtolower($name);

		# Get all the countries
		$countries = Info::getInstance()->getInfo("country");

		# Run thru each country
		foreach($countries as $country){
			# If there is a match, return the ISO-alpha2
			if($name == strtolower($country['name'])){
				return $country['country_code'];
			}
		}

		return $name;
	}

	public static function getCountryFromISOAlpha2(?string $iso2): ?string
	{
		if(!$iso2){
			return $iso2;
		}

		$countries = Info::getInstance()->getInfo("country");
		if(($key = array_search($iso2, array_column($countries, 'country_code'))) === false){
			return $iso2;
		}

		return $countries[$key]['name'];
	}


	/**
	 * Given a user ID, get that user's local currency code.
	 * Based on the geolocation data collected from each user.
	 *
	 * @param string|null $user_id
	 *
	 * @return string|null Three letter currency code
	 */
	public static function getLocalUserCurrency(?string $user_id): ?string
	{
		if(!$user_id){
			return NULL;
		}

		$sql = Factory::getInstance();

		if(!$currency = $sql->select([
			"columns" => "ip",
			"table" => "connection",
			"join" => [[
				"columns" => false,
				"table" => "geolocation",
				"on" => "ip",
			], [
				"columns" => "currency_code",
				"table" => "country",
				"on" => [
					"country_code" => ["geolocation", "country_code"],
				],
			]],
			"where" => [
				["closed", "IS", NULL],
				"user_id" => $user_id,
			],
			"order_by" => [
				"created" => "DESC",
			],
			"limit" => 1,
		])){
			return NULL;
		}

		return $currency['geolocation'][0]['country'][0]['currency_code'];
	}

	/**
	 * @param string   $country_code    The ISO alpha-2 country code, case-insensitive
	 * @param int      $postcode_length The length of the postcode field
	 * @param int|null $name_length     The max length of a place name field (Default: 170, the max length of any
	 *                                  country's place name)
	 */
	private function createCountryPostcodeTable(string $country_code, string $address_table, string $postcode_col, array $address_cols): void
	{
		$country_code = strtolower($country_code);

		$postcode = $this->sql->select([
			"columns" => [
				"length" => ["LENGTH", $postcode_col],
			],
			"db" => $this->db,
			"table" => $address_table,
			"where" => [
				"country_code" => $country_code,
			],
			"include_removed" => true,
			"limit" => 1,
			"order_by" => [
				"length" => "DESC",
			],
		]);

		foreach($address_cols as $col){
			$name = $this->sql->select([
				"columns" => [
					"length" => ["LENGTH", $col],
				],
				"db" => $this->db,
				"table" => $address_table,
				"where" => [
					"country_code" => $country_code,
				],
				"include_removed" => true,
				"limit" => 1,
				"order_by" => [
					"length" => "DESC",
				],
			]);

			$name_length = $name_length > $name['length'] ? $name_length : $name['length'];
		}

		$query = <<<EOF
CREATE TABLE IF NOT EXISTS `{$this->db}`.`{$country_code}_postcode` (
  `postcode` char({$postcode['length']}) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar({$name_length}) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  KEY `postcode` (`postcode`),
  KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOF;
		$this->sql->run($query);
	}

	private function addToTheSet(array &$set, array $row, string $key, string $postcode_col): void
	{
		# Ensure the key value exists
		if(!$name = $row[$key]){
			return;
		}

		$postcode = $row[$postcode_col];

		# Treat the "Name (Alternative / Alternative)" string differently
		if(preg_match("/(.*?) \(([^\)]+)\)/", $name, $matches)){
			$names = preg_split("/\s?\/\s?/", $matches[2]);
			$names[] = $matches[1];
		}

		else {
			$names = preg_split("/\s?\/\s?/", $name);
		}

		foreach($names as $name){
			if(!$name = trim($name)){
				continue;
			}

			$id = "{$postcode}|{$name}";

			# If the postcode/name already exists, don't insert it again
			if($set[$id]){
				return;
			}

			$set[$id] = [
				"postcode" => $postcode,
				"name" => $name,
			];
		}
	}

	private array $ignore_list = [
		'ar' => "The Argentina data file only contains the first 5 positions of the postal code.",
		"ca" => "For Canada we have only the first letters of the full postal codes (for copyright reasons).",
		"cl" => "For Chile we have only the first digits of the full postal codes (for copyright reasons).",
		"ie" => "For Ireland we have only the first letters of the full postal codes (for copyright reasons).",
		"mt" => "For Malta we have only the first letters of the full postal codes (for copyright reasons).",
		"br" => "For Brazil only major postal codes are available (only the codes ending with -000 and the major code per municipality).",
	];

	private function setPostcodeRegex(string $country_code): void
	{
		# Load the relevant regex (if one exists)
		$pattern = $this->country_info[strtoupper($country_code)]['Postal Code Regex'];

		# Trim away the ^ and $ from the beginning and end of the regex
		if(substr($pattern, 0, 1) == "^"){
			$pattern = substr($pattern, 1);
		}
		if(substr($pattern, -1) == "$"){
			$pattern = substr($pattern, 0, -1);
		}

		if($pattern){
			//if a pattern has been given

			# Delete existing regex for this country code
			$this->sql->run("DELETE FROM `{$this->db}`.`pattern` WHERE `pattern_id` = '{$country_code}';");

			# Insert the pattern
			$this->sql->insert([
				"include_meta" => false,
				"db" => $this->db,
				"table" => "pattern",
				"set" => [
					"pattern_id" => strtolower($country_code),
					"country_code" => strtoupper($country_code),
					"pattern" => $pattern,
				],
			]);
		}

		else {
			//if there is no pattern given

			if(!$this->sql->select([
				"include_removed" => true,
				"db" => $this->db,
				"table" => "pattern",
				"where" => [
					"pattern_id" => strtolower($country_code),
					"country_code" => strtoupper($country_code),
				],
			])){
				//and no current pattern exists

				# Insert a blank pattern and warn the user
				$this->sql->insert([
					"include_meta" => false,
					"db" => $this->db,
					"table" => "pattern",
					"set" => [
						"pattern_id" => strtolower($country_code),
						"country_code" => strtoupper($country_code),
						"pattern" => NULL,
					],
				]);

				$this->log->warning([
					"container" => "#ui-view",
					"message" => "No regex pattern found for post codes from {$this->country_info[strtoupper($country_code)]['Country']}.",
				], true);
			}
		}
	}

	/**
	 * @param string              $country_code  The ISO alpha-2 country code
	 * @param bool|null           $refresh       Whether to refresh the existing data
	 * @param string|null         $address_table The name of the table containing the addresses (default:
	 *                                           `all_address`)
	 * @param string|null         $postcode_col  The name of the column containing the postcode (default:
	 *                                           `postal_code`)
	 * @param array|string[]|null $address_cols  An array of all the columns that contain place names (default:
	 *                                           `place_name`, `admin_name1`, `admin_name2`, `admin_name3`)
	 */
	private function importOneCountry(string $country_code, ?bool $refresh = NULL, ?string $address_table = "all_address", ?string $postcode_col = "postal_code", ?array $address_cols = ["place_name", "admin_name1", "admin_name2", "admin_name3"]): void
	{
		# Generate the table name
		$table = strtolower("{$country_code}_postcode");

		if($this->sql->tableExists($this->db, $table)){
			//if the table already exists
			if(!$refresh){
				//and we're not refreshing
				$this->log->info([
					"container" => "#ui-view",
					"title" => "{$this->country_info[strtoupper($country_code)]['Country']} already imported",
					"message" => "The postcodes belonging to {$this->country_info[strtoupper($country_code)]['Country']} have already been imported and are not due to be refreshed.",
				], true);
				return;
				//we're done
			}

			# Drop the existing table
			$this->sql->run("DROP TABLE `{$this->db}`.`{$table}`;");
		}

		# The all address table includes some countries that we're ignoring
		if($address_table == "all_address" && $this->ignore_list[strtolower($country_code)]){
			$this->log->warning([
				"container" => "#ui-view",
				"title" => "{$this->country_info[strtoupper($country_code)]['Country']} postcodes ignored",
				"message" => $this->ignore_list[strtolower($country_code)] . " Thus the postcodes will not be auto-imported.",
			], true);
			return;
		}

		# Ensure the country is represented
		if(!$this->sql->select([
			"flat" => true,
			"db" => $this->db,
			"table" => $address_table,
			"where" => [
				"country_code" => $country_code,
			],
			"include_removed" => true,
			"limit" => 1,
		])){
			return;
		}

		# Set the postcode regex in the pattern table
		$this->setPostcodeRegex($country_code);

		# Generate the table (if it doesn't exist already)
		$this->createCountryPostcodeTable($country_code, $address_table, $postcode_col, $address_cols);

		$set = [];
		$start = 0;
		$length = 15000;

		# Load the rows in batches of 10k
		while($rows = $this->sql->select([
			"flat" => true,
			"db" => $this->db,
			"table" => $address_table,
			"where" => [
				"country_code" => $country_code
			],
			"include_removed" => true,
			"order_by" => [
				$postcode_col => "ASC"
			],
			"start" => $start,
			"length" => $length,
		])) {
			if($start > 0){
				$this->log->info([
					"container" => "#ui-view",
					"message" => "Processed " . str::number($start, NULL, NULL, 0) . " records from the {$this->country_info[strtoupper($country_code)]['Country']} post code table.",
				], true);
			}
			# Import all the rows
			foreach($rows as $row){
				foreach($address_cols as $col){
					$this->addToTheSet($set, $row, $col, $postcode_col);
				}

				# Import the rows in batches of 1,000
				if(count($set) >= 1000){
					$this->sql->insert([
						"include_meta" => false,
						"db" => $this->db,
						"table" => $table,
						"set" => array_values($set),
					]);
					$set_count += count($set);
					$set = [];
				}
			}
			$start += $length;

			$this->sql->freeUpMemory();
		}

		# Get the stragglers
		if($set){
			$this->sql->insert([
				"include_meta" => false,
				"db" => $this->db,
				"table" => $table,
				"set" => array_values($set),
			]);
			$set_count += count($set);
			$set = [];
		}

		# Run the deduplication queries
		$this->sql->run("ALTER TABLE		`{$this->db}`.`{$table}` ADD `to_keep` BOOLEAN;");
		$this->sql->run("ALTER TABLE		`{$this->db}`.`{$table}` ADD CONSTRAINT `prevent_duplicate` UNIQUE (`postcode`, `name`, `to_keep`);");
		$this->sql->run("UPDATE IGNORE	`{$this->db}`.`{$table}` SET `to_keep` = true;");
		$this->sql->run("DELETE FROM		`{$this->db}`.`{$table}` WHERE `to_keep` IS NULL;");
		$this->sql->run("ALTER TABLE		`{$this->db}`.`{$table}` DROP `to_keep`;");

		$final_row_count = $this->sql->select([
			"count" => "postcode",
			"include_removed" => true,
			"db" => $this->db,
			"table" => $table,
		]);

		$narrative = "Imported " . str::number($set_count, NULL, NULL, 0) . " place names.";

		if($final_row_count < $set_count){
			$narrative .= " This was deduplicated to " . str::number($final_row_count, NULL, NULL, 0) . ".";
		}

		$this->log->success([
			"container" => "#ui-view",
			"title" => "Import of {$this->country_info[strtoupper($country_code)]['Country']} complete",
			"message" => $narrative,
		], true);
	}

	private function loadCountryInfo(): void
	{
		foreach($this->sql->select([
			"db" => $this->db,
			"table" => "country_info",
			"include_removed" => true,
		]) as $country){
			$this->country_info[$country['ISO']] = $country;
		}
	}

	private function importCountry(string $country_code, ?bool $refresh = NULL): void
	{
		$country_code = strtolower($country_code);

		switch($country_code) {
		case 'br':
			$this->importOneCountry($country_code, $refresh, "br_address", "zipcode", ["state", "municipality", "neighbourhood", "public_place"]);
			break;
		case 'ca':
			$this->importOneCountry($country_code, $refresh, "ca_address", "POSTAL_CODE", ["CITY"]);
			break;
		default:
			$this->importOneCountry($country_code, $refresh);
			break;
		}
	}

	public function import(array $a): bool
	{
		extract($a);

		# Importing larger countries will take some time
		ini_set('max_execution_time', 0);

		# Load all the country info as an array
		$this->loadCountryInfo();

		if($vars['country_code']){
			$this->importCountry($vars['country_code'], $vars['refresh']);
		}

		else {
			foreach($this->country_info as $country){
				$this->importCountry($country['ISO'], $vars['refresh']);
			}
		}

		return true;
	}
}