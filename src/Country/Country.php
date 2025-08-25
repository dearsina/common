<?php

namespace App\Common\Country;

use API\ExchangeRates\ExchangeRates;
use App\Address\JaroWinkler;
use App\Common\Prototype;
use App\Common\SQL\Factory;
use App\Common\SQL\Info\Info;
use App\Common\str;
use App\Translation\Translator;

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
				"connection_id" => "DESC",
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
	public static function getAllCountries(?bool $formatted = NULL, ?string $language_id = NULL): array
	{
		$info = Info::getInstance();

		if($language_id){
			$t = new Translator();
			$t->setToLanguageId($language_id);
			$countries = $t->translateCountries();
		}
		else {
			$countries = $info->getInfo("country");
		}

		foreach($countries as $country){
			if($formatted){
				$country_options[$country['country_code']] = [
					"title" => $country['name'],
					"icon" => self::getIconFromISOAlpha2($country['country_code']),
				];
			}

			else {
				$country_options[$country['country_code']] = $country['name'];
			}

		}

		return $country_options;
	}

	public static function getIconFromISOAlpha2(?string $iso2): ?array
	{
		if(!$iso2){
			return NULL;
		}

		$iso2 = strtoupper($iso2);

		foreach(Info::getInstance()->getInfo("country") as $country){
			if($country['country_code'] == $iso2){
				break;
			}
		}

		return [
			"type" => "flag",
			"name" => $iso2,
			"alt" => $country['name'],
		];
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

	public static function convertIso2toIso3(?string $iso2): ?string
	{
		if(!$iso2){
			return NULL;
		}

		$countries = Info::getInstance()->getInfo("country");
		$key = array_search($iso2, array_column($countries, 'country_code'));

		if($key === false){
			return $iso2;
		}

		return $countries[$key]['iso_alpha-3'];
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
	 * Given a country name string (case-insensitive), will return
	 * the ISO-alpha2 code of the country.
	 *
	 * Returns the original country name string if no match is found.
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

		# Run through each country
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
		# Ensure the string is only two characters long
		if(strlen($iso2) != 2){
			return $iso2;
		}

		# Ensure the string has been passed, and is in uppercase
		if(!$iso2 = strtoupper(trim($iso2))){
			return NULL;
		}

		$countries = Info::getInstance()->getInfo("country");
		if(($key = array_search($iso2, array_column($countries, 'country_code'))) === false){
			return $iso2;
		}

		return $countries[$key]['name'];
	}

	/**
	 * Will return the relevant ISO alpha2 country code
	 * if it can be found.
	 *
	 * Will return NULL if it cannot be found. For example
	 * "com" will return NULL, as it is not a country code.
	 *
	 * @param string|null $cc_tld
	 *
	 * @return string|null
	 * @throws \Exception
	 */
	public static function getISOAlpha2FromCCTLD(?string $cc_tld): ?string
	{
		# Ensure the string has been passed, and is in uppercase
		if(!$cc_tld = strtolower(trim($cc_tld))){
			return NULL;
		}

		# Ensure the string is only two characters long
		if(strlen($cc_tld) != 2){
			return NULL;
		}

		$countries = Info::getInstance()->getInfo("country");
		if(($key = array_search($cc_tld, array_column($countries, 'cc_tld'))) === false){
			return NULL;
		}

		return $countries[$key]['country_code'];
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
				"connection_id" => "DESC",
			],
			"limit" => 1,
		])){
			return NULL;
		}

		return $currency['geolocation'][0]['country'][0]['currency_code'];
	}

	/**
	 * Given a country name or nationality, will return the
	 * ISO-2 country code.
	 * Allows for fuzzy matching.
	 *
	 * @param string|null $val        The value that could be a country code, name or nationality
	 * @param float|null  $fuzzy      value between 0 and 1 corresponding to the level of fuzziness (Jaro-Winkler score)
	 * @param string|null $return_col The column to return. Defaults to "country_code"
	 *
	 * @return string|null Returns NULL if no match can be found.
	 * @throws \Exception
	 */
	public static function getCountryCode(?string $val, ?float $fuzzy = NULL, ?string $return_col = "country_code"): ?string
	{
		# A value must be included
		if(!$val){
			return NULL;
		}

		$val = self::simplifyCountryName($val);

		# Load the country array
		$countries = Info::getInstance()->getInfo("country");

		# Load the columns to match (no fuzzy)
		$columns_to_match = [
			"country_code",
			"iso_alpha-3",
			"alt_iso_alpha-3",
		];

		# Load the columns to search through (optionally fuzzy)
		$columns_to_search = [
			"name",

			# Can contain more than one value, pipe-delimited
			"alt_name",
			"nationality",
		];

		# Match on the country code, ISO alpha-3 or alt ISO alpha-3
		foreach($countries as $country){
			foreach($columns_to_match as $col){
				if(!$country[$col]){
					// Not all countries have all columns
					continue;
				}
				if($val == strtolower($country[$col])){
					return $country[$return_col];
				}
			}
		}

		# Match on name, alternative names and nationalities
		foreach($countries as $country){
			foreach($columns_to_search as $col){
				if(!$country[$col]){
					// Not all countries have all columns
					continue;
				}

				foreach(explode("|", $country[$col]) as $name){
					// The alt_name and nationality columns can contain more than one value, pipe-delimited

					$name = self::simplifyCountryName($name);

					if($name == $val){
						return $country[$return_col];
					}
				}
			}
		}

		# Match FUZZY on name, alternative names and nationalities
		if($fuzzy){
			// If fuzzy is enabled
			foreach($countries as $country){
				foreach($columns_to_search as $col){
					if(!$country[$col]){
						// Not all countries have all columns
						continue;
					}

					foreach(explode("|", $country[$col]) as $name){
						// The alt_name and nationality columns can contain more than one value, pipe-delimited

						$name = self::simplifyCountryName($name);

						if(JaroWinkler::compare($name, $val) >= $fuzzy){
							return $country[$return_col];
						}
					}
				}
			}
		}

		return NULL;
	}

	/**
	 * Simplifies a country names for comparison.
	 *
	 * @param string $val
	 *
	 * @return string
	 */
	private static function simplifyCountryName(string $val): string
	{
		# Strip any suffixed text in square brackets
		$val = preg_replace("/\s*\[.*?\]\s*$/", "", $val);

		# Replace & with "and"
		$val = str_replace("&", "and", $val);

		# Replace "St " with "Saint "
		$val = preg_replace("/\bSt\s+/i", "Saint ", $val);

		# Strip "the" from the beginning
		$val = preg_replace("/^the\s+/i", "", $val);

		$val = trim($val);

		$val = strtolower($val);

		return $val;
	}
}