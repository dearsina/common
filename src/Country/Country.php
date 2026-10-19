<?php

namespace App\Common\Country;

use API\ExchangeRates\ExchangeRates;
use App\Address\JaroWinkler;
use App\Common\Prototype;
use App\Common\SQL\Factory;
use App\Common\SQL\Info\Info;
use App\Common\str;
use App\Translation\Translator;
use App\UI\Icon;
use App\Workflow\Workflow;

class Country extends Prototype {
	public const CODE_FORMAT_SETTING = "country_code_format";
	public const ALPHA_2 = "alpha-2";
	public const ALPHA_3 = "alpha-3";

	const CURRENCY_EXCEPTIONS = [
		"AUD" => [
			"icon" => [
				"type" => "flag",
				"name" => "au",
			],
			"name" => "Australian Dollar",
		],
		"ANG" => [
			"icon" => "globe",
		],
		"CHF" => [
			"icon" => [
				"type" => "flag",
				"name" => "ch",
			],
			"name" => "Swiss Franc",
		],
		"CZK" => [
			"icon" => [
				"type" => "flag",
				"name" => "cz",
			],
			"name" => "Czechia",
		],
		"DKK" => [
			"icon" => [
				"type" => "flag",
				"name" => "dk",
			],
			"name" => "Denmark",
		],
		"EUR" => [
			"icon" => [
				"type" => "flag",
				"name" => "eu",
			],
			"name" => "Euro",
		],
		"USD" => [
			"icon" => [
				"type" => "flag",
				"name" => "us",
			],
			"name" => "US Dollar",
		],
		"GBP" => [
			"icon" => [
				"type" => "flag",
				"name" => "gb",
			],
			"name" => "Pound Sterling",
			"alt" => "UK, British",
		],
		"ILS" => [
			"icon" => [
				"type" => "flag",
				"name" => "il",
			],
			"name" => "Israeli New Shekel",
		],
		"MAD" => [
			"icon" => "globe",
		],
		"NOK" => [
			"icon" => [
				"type" => "flag",
				"name" => "no",
			],
			"name" => "Norway",
		],
		"NZD" => [
			"icon" => [
				"type" => "flag",
				"name" => "nz",
			],
			"name" => "New Zealand",
		],
		"TRY" => [
			"icon" => [
				"type" => "flag",
				"name" => "tr",
			],
			"name" => "Turkish Lira",
		],
		"XAF" => [
			"icon" => "earth-africa",
			"name" => "Central African CFA franc",
		],
		"XCD" => [
			"icon" => "earth-americas",
			"name" => "East Caribbean Dollar",
		],
		"XOF" => [
			"icon" => "earth-africa",
			"name" => "West African CFA franc",
		],
		"XPF" => [
			"icon" => "earth-oceania",
			"name" => "CFP franc",
		],
	];

	public ?string $db = "address";

	public static function getCurrencyCountries(?string $currency_code = NULL): array
	{
		$countries = \App\Common\SQL\Info\Info::getInstance()->getInfo([
			"columns" => [
				"currency_code",
				"country_codes" => [
					"group_concat",
					[
						"distinct" => true,
						"columns" => "country_code",
						"order_by" => [
							"country" => "ASC",
						],
						"separator" => ", ",
					],
				],
				"names" => [
					"group_concat",
					[
						"distinct" => true,
						"columns" => "name",
						"order_by" => [
							"country" => "ASC",
						],
						"separator" => ", ",
					],
				],
				"alt_name"
			],
			"rel_table" => "country",
			"order_by" => [
				"currency_code" => "ASC",
			],
		]);

		# If we're only returning a single country
		if($currency_code){
			$countries = array_filter($countries, function($country) use ($currency_code){
				return $country['currency_code'] == $currency_code;
			});
			return reset($countries);
		}

		return $countries;
	}

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
		$exchange_rates = ExchangeRates::get();

		foreach(self::getCurrencyCountries() as $country){
			# If we don't have an exchange rate for the currency, ignore it
			if(!$exchange_rates[$country['currency_code']]){
				continue;
			}

			$currency_code_options[$country['currency_code']] = self::getCurrencyCodeOption($country['currency_code']);
		}

		return $currency_code_options;
	}

	public static function getCurrencyCodeOption(string $currency_code): array
	{
		$country = self::getCurrencyCountries($currency_code);

		if(isset(self::CURRENCY_EXCEPTIONS[$country['currency_code']])){
			$exception = self::CURRENCY_EXCEPTIONS[$country['currency_code']];
			$icon = $exception['icon'];
			$country_name = $exception['name'] ?? $country['names'];
			$alt = $exception['alt'] ?? NULL;
		}
		else {
			$country['country_code'] = $country['country_codes'];
			$country_name = $country['names'];
			$icon = [
				"type" => "flag",
				"name" => $country['country_code'],
			];
			$alt = $country['alt_name'];
		}

		$currency_code = str::monospace($country['currency_code']);
		$country_name = explode(",", $country_name)
				|> (fn($x) => array_map(function($name){
					return Workflow::getPrefixWrapper($name);
				}, $x))
				|> (fn($x) => implode(" ", $x));

		return [
			"title" => "{$country['currency_code']} {$country_name} {$alt}",
			"html" => Icon::generate($icon)."{$currency_code} {$country_name}",
		];
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

	/**
	 * Resolve the ISO country-code format configured for a form field.
	 *
	 * Alpha-2 is the backward-compatible default. Alpha-3 is returned only when
	 * the {@see self::CODE_FORMAT_SETTING} setting explicitly contains
	 * {@see self::ALPHA_3}; missing, NULL, or unsupported values fall back to
	 * {@see self::ALPHA_2}.
	 *
	 * @param array{country_code_format?: string}|null $settings Form-field settings containing the optional storage format.
	 *
	 * @return string One of {@see self::ALPHA_2} or {@see self::ALPHA_3}.
	 */
	public static function getStorageFormat(?array $settings = NULL): string
	{
		return ($settings[self::CODE_FORMAT_SETTING] ?? NULL) === self::ALPHA_3
			? self::ALPHA_3
			: self::ALPHA_2;
	}

	/**
	 * Return the country-table column associated with the configured code format.
	 *
	 * The returned column can be passed to {@see self::getCountryCode()} as its
	 * return column, allowing any recognised country representation to be
	 * converted directly to the configured storage value.
	 *
	 * @param array{country_code_format?: string}|null $settings Form-field settings containing the optional storage format.
	 *
	 * @return string `country_code` for alpha-2 or `iso_alpha-3` for alpha-3.
	 */
	public static function getCodeColumn(?array $settings = NULL): string
	{
		return self::getStorageFormat($settings) === self::ALPHA_3
			? "iso_alpha-3"
			: "country_code";
	}

	/**
	 * Return the number of characters required by the configured ISO code format.
	 *
	 * @param array{country_code_format?: string}|null $settings Form-field settings containing the optional storage format.
	 *
	 * @return int `2` for alpha-2 or `3` for alpha-3.
	 */
	public static function getCodeLength(?array $settings = NULL): int
	{
		return self::getStorageFormat($settings) === self::ALPHA_3 ? 3 : 2;
	}

	/**
	 * Convert a recognised country representation to the configured stored code.
	 *
	 * Alpha-2 codes, alpha-3 codes, alternative alpha-3 codes, country names,
	 * alternative names, and nationalities are accepted through
	 * {@see self::getCountryCode()}. The returned code is always uppercase.
	 * NULL and blank strings are returned unchanged, while an unrecognised
	 * non-blank value returns NULL.
	 *
	 * @param string|null                              $value    Country code, name, nationality, NULL, or a blank string.
	 * @param array{country_code_format?: string}|null $settings Form-field settings containing the desired storage format.
	 *
	 * @return string|null The uppercase alpha-2/alpha-3 code, the unchanged empty value, or NULL when unrecognised.
	 * @throws \Exception If the country reference data cannot be loaded.
	 */
	public static function normalise(?string $value, ?array $settings = NULL): ?string
	{
		if($value === NULL || trim($value) === ""){
			return $value;
		}

		$code = self::getCountryCode($value, NULL, self::getCodeColumn($settings));
		return $code ? strtoupper($code) : NULL;
	}

	/**
	 * Recursively normalise one or more country values for storage.
	 *
	 * Arrays are traversed recursively without reindexing, so associative and
	 * multiplier keys are retained. Every non-NULL scalar value is cast to a
	 * string and passed to {@see self::normalise()}; NULL remains NULL.
	 *
	 * @param mixed                                      $value    Scalar country value, nested array of values, or NULL.
	 * @param array{country_code_format?: string}|null   $settings Form-field settings containing the desired storage format.
	 *
	 * @return mixed The normalised scalar, NULL, or an array matching the input shape.
	 * @throws \Exception If the country reference data cannot be loaded.
	 */
	public static function normaliseValue($value, ?array $settings = NULL)
	{
		if(is_array($value)){
			foreach($value as $key => $item){
				$value[$key] = self::normaliseValue($item, $settings);
			}
			return $value;
		}

		if($value === NULL){
			return NULL;
		}

		return self::normalise((string)$value, $settings);
	}

	/**
	 * Normalise a database-shaped scalar, array, or JSON-encoded array.
	 *
	 * A string containing a valid JSON array is decoded, recursively normalised,
	 * and JSON-encoded again so its database representation remains a string.
	 * Native arrays remain arrays, and all other values are handled by
	 * {@see self::normaliseValue()}.
	 *
	 * @param mixed                                    $value    Stored scalar, native array, JSON-encoded array, or NULL.
	 * @param array{country_code_format?: string}|null $settings Form-field settings containing the desired storage format.
	 *
	 * @return mixed A normalised value preserving whether an input array was native or JSON encoded.
	 * @throws \Exception If the country reference data cannot be loaded.
	 */
	public static function normaliseStoredValue($value, ?array $settings = NULL)
	{
		if(is_string($value)){
			$decoded = json_decode($value, true);
			if(json_last_error() === JSON_ERROR_NONE && is_array($decoded)){
				return json_encode(self::normaliseValue($decoded, $settings));
			}
		}

		return self::normaliseValue($value, $settings);
	}

	/**
	 * Determine whether a submitted value is a valid code in the configured format.
	 *
	 * Validation is case-insensitive but format-sensitive: an alpha-2 code is not
	 * valid for an alpha-3 field, and vice versa. Country names and nationalities
	 * are deliberately rejected even when they identify a known country. NULL and
	 * blank values are valid here because required-field validation is handled by
	 * the caller.
	 *
	 * @param string|null                              $value    Submitted country code, NULL, or a blank string.
	 * @param array{country_code_format?: string}|null $settings Form-field settings containing the required code format.
	 *
	 * @return bool TRUE when the value is blank or exactly represents a known code in the configured format.
	 * @throws \Exception If the country reference data cannot be loaded.
	 */
	public static function isValidStoredCode(?string $value, ?array $settings = NULL): bool
	{
		if($value === NULL || trim($value) === ""){
			return true;
		}

		$normalised = self::normalise($value, $settings);
		return $normalised !== NULL && $normalised === strtoupper(trim($value));
	}

	/**
	 * Resolve a country value to its full canonical country name for display.
	 *
	 * Both alpha-2 and alpha-3 codes are supported, as are the other country
	 * representations recognised by {@see self::getCountryCode()}. NULL and blank
	 * strings are returned unchanged. Unknown values are also returned unchanged
	 * so historical or externally supplied data remains visible to the user.
	 *
	 * @param string|null $value Country code, name, nationality, NULL, or a blank string.
	 *
	 * @return string|null The canonical country name, or the original value when empty or unrecognised.
	 * @throws \Exception If the country reference data cannot be loaded.
	 */
	public static function getName(?string $value): ?string
	{
		if($value === NULL || trim($value) === ""){
			return $value;
		}

		if(!$iso2 = self::getCountryCode($value)){
			return $value;
		}

		return self::getCountryFromISOAlpha2($iso2) ?: $value;
	}

	/**
	 * Build country dropdown options keyed by the configured stored-code format.
	 *
	 * Option labels always remain full country names. When `$formatted` is TRUE,
	 * each value also contains the existing flag-icon metadata. The `only` and
	 * `except` filters may contain any country representation recognised by
	 * {@see self::getCountryCode()}; they are canonicalised to alpha-2 internally
	 * so existing geofencing settings remain compatible. Exclusions take
	 * precedence when the same country appears in both filters.
	 *
	 * @param bool|null                                 $formatted        TRUE for title/icon option arrays; otherwise plain names.
	 * @param string|null                               $language_id      Optional language UUID used to translate country names.
	 * @param array{country_code_format?: string}|null  $settings         Form-field settings containing the desired option-key format.
	 * @param array<array-key, string>|string|null      $only_countries   Optional allow-list, either decoded or JSON encoded.
	 * @param array<array-key, string>|string|null      $except_countries Optional deny-list, either decoded or JSON encoded.
	 *
	 * @return array<string, string|array<string, mixed>> Options keyed by uppercase alpha-2 or alpha-3 country code.
	 * @throws \Exception If country or translation reference data cannot be loaded.
	 */
	public static function getOptions(
		?bool $formatted = NULL,
		?string $language_id = NULL,
		?array $settings = NULL,
		array|string|null $only_countries = NULL,
		array|string|null $except_countries = NULL
	): array {
		$options = self::getAllCountries($formatted, $language_id);
		$country_options = [];
		$only_countries = self::normaliseCountryFilter($only_countries);
		$except_countries = self::normaliseCountryFilter($except_countries);
		$only = self::getIso2Lookup($only_countries);
		$except = self::getIso2Lookup($except_countries);
		$has_only_filter = (bool)$only_countries;

		foreach($options as $iso2 => $option){
			$iso2 = strtoupper($iso2);

			if(($has_only_filter && !isset($only[$iso2])) || isset($except[$iso2])){
				continue;
			}

			if(self::getStorageFormat($settings) !== self::ALPHA_3){
				$country_options[$iso2] = $option;
				continue;
			}

			$iso3 = self::convertIso2toIso3($iso2);
			if(!$iso3 || $iso3 === $iso2){
				continue;
			}

			$country_options[strtoupper($iso3)] = $option;
		}

		return $country_options;
	}

	/**
	 * Convert a decoded, JSON-encoded, or scalar country filter to an array.
	 *
	 * @param array<array-key, string>|string|null $countries Stored country-filter value.
	 *
	 * @return array<array-key, string> Country values in array form.
	 */
	private static function normaliseCountryFilter(array|string|null $countries): array
	{
		if(is_array($countries)){
			return $countries;
		}

		if($countries === NULL || trim($countries) === ""){
			return [];
		}

		$decoded = json_decode($countries, true);
		if(json_last_error() === JSON_ERROR_NONE && is_array($decoded)){
			return $decoded;
		}

		return [$countries];
	}

	/**
	 * Canonicalise a country filter into an alpha-2 lookup set.
	 *
	 * Unrecognised values are omitted. The lookup is used internally for constant-
	 * time allow-list and deny-list checks while building country options.
	 *
	 * @param array<array-key, string>|null $countries Country codes, names, or nationalities to canonicalise.
	 *
	 * @return array<string, true> Uppercase alpha-2 codes as keys with TRUE values.
	 * @throws \Exception If the country reference data cannot be loaded.
	 */
	private static function getIso2Lookup(?array $countries): array
	{
		$lookup = [];

		foreach($countries ?: [] as $country){
			if($iso2 = self::getCountryCode((string)$country)){
				$lookup[strtoupper($iso2)] = true;
			}
		}

		return $lookup;
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
	 * Given an ISO alpha-2 country code,
	 * will return an array of all known names
	 * for that country, including:
	 * - ISO alpha-2 code
	 * - ISO alpha-3 code
	 * - Alternative ISO alpha-3 code
	 * - Country name
	 * - Alternative country names
	 * - ccTLD
	 *
	 * For example, for "GB", it will return:
	 * ["GB", "GBR", "UK", "uk", "United Kingdom", "Britain", "Great Britain"]
	 *
	 * If the country code cannot be found, will return NULL.
	 *
	 * @param string|null $iso2
	 *
	 * @return array|null
	 * @throws \Exception
	 */
	public static function getAllCountryNamesFromIsoAlpha2(?string $iso2, ?array $include = NULL): ?array
	{
		# Ensure the string is only two characters long
		if(strlen($iso2) != 2){
			return NULL;
		}

		# Ensure the string has been passed, and is in uppercase
		if(!$iso2 = strtoupper(trim($iso2))){
			return NULL;
		}

		$countries = Info::getInstance()->getInfo("country");
		if(($key = array_search($iso2, array_column($countries, 'country_code'))) === false){
			return NULL;
		}

		$country = $countries[$key];

		if(!$include){
			$include = [
				"country_code",
				"iso_alpha-3",
				"alt_iso_alpha-3",
				"cc_tld",
				"name",
				"alt_name",
			];
		}

		$names = [];
		foreach($include as $col){
			if($col == "alt_name"){
				if($country['alt_name']){
					$names = array_merge($names, explode("|", $country['alt_name']));
				}
			}
			else {
				$names[] = $country[$col];
			}
		}

		return array_filter(array_unique($names));
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
