<?php

namespace App\Common\Country;

use App\Common\SQL\Factory;
use App\Common\SQL\Info\Info;

class Country {
	/**
	 * Get an array of currency code options
	 * to use in a dropdown select.
	 *
	 * @return array
	 */
	public static function getCurrencyCodeOptions(): array
	{
		$sql = Factory::getInstance();
		foreach($sql->select([
			"columns" => [
				"currency_code",
				"countries" => ["group_concat", [
					"distinct" => true,
					"columns" => "name",
					"order_by" => [
						"country" => "ASC"
					],
					"separator" => ", "
				]
				]
			],
			"table" => "country",
			"order_by" => [
				"currency_code" => "ASC"
			]
		]) as $id => $country){
			$currency_code_options[$country['currency_code']] = "{$country['currency_code']}, used by {$country['countries']}";
		}

		return $currency_code_options;
	}

	public static function getAllCountries(): array
	{
		$info = Info::getInstance();

		foreach($info->getInfo("country") as $country){
			$country_options[$country['country_code']] = $country['name'];
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
		if(($key = array_search($iso3, array_column($countries, 'iso_alpha-3'))) === FALSE){
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
		if(($key = array_search($iso2, array_column($countries, 'country_code'))) === FALSE){
			return $iso2;
		}

		return $countries[$key]['nationality'];
	}

	public static function getCountryFromISOAlpha2(?string $iso2): ?string
	{
		if(!$iso2){
			return $iso2;
		}

		$countries = Info::getInstance()->getInfo("country");
		if(($key = array_search($iso2, array_column($countries, 'country_code'))) === FALSE){
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
				"on" => "ip"
			],[
				"columns" => "currency_code",
				"table" => "country",
				"on" => [
					"country_code" => ["geolocation", "country_code"]
				]
			]],
			"where" => [
				["closed", "IS", NULL],
				"user_id" => $user_id
			],
			"order_by" => [
				"created" => "DESC"
			],
			"limit" => 1
		])){
			return NULL;
		}

		return $currency['geolocation'][0]['country'][0]['currency_code'];
	}
}