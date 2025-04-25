<?php

namespace App\Common\Language;

use App\Common\Connection\Connection;
use App\Common\Geolocation\Geolocation;
use App\Common\Prototype;
use App\Common\SQL\Factory;
use App\Common\str;
use App\UI\Badge;
use App\UI\Icon;

/**
 * Language class that can handle generic language tasks.
 * Excpects there to be a language table.
 *
 */
class Language extends Prototype {
	/**
	 * The default language that strings are translated from.
	 */
	const DEFAULT_FROM_LANGUAGE_ID = "en";
	const DEFAULT_TO_LANGUAGE_ID = "en";

	public static function getLanguagesWithCountryCodes(): array
	{
		$languages = \App\Common\SQL\Info\Info::getInstance()->getInfo([
			"table" => "language",
			"where" => [
				["country_code", "IS NOT", NULL],
			],
		]);

		return $languages;
	}

	public static function getLanguagesWithIsoCodes(): array
	{
		return \App\Common\SQL\Info\Info::getInstance()->getInfo([
			"distinct" => true,
			"columns" => [
				"language_id" => "iso_639-1",
				"title",
				"script",
				"direction",
			],
			"table" => "language",
			"where" => [
				["iso_639-1", "IS NOT", NULL],
				"LENGTH(`language`.`language_id`) = 2",
			],
		]);
	}

	public static function getScriptOptions(): array
	{
		$languages = \App\Common\SQL\Info\Info::getInstance()->getInfo([
			"distinct" => true,
			"columns" => [
				"script",
				"direction",
			],
			"table" => "language",
			"order_by" => [
				"script" => "ASC",
			],
		]);

		$options = [];
		foreach($languages as $language){
			if(!$title = $language['script']){
				continue;
			}

			$badges = [];
			$badges[] = Badges::direction($language);

			$title .= " ";
			$title .= Badge::generate($badges);

			$options[$language['script']] = [
				"title" => strip_tags($title),
				"html" => $title,
			];
		}

		return $options;
	}

	/**
	 * @param string|array|null $language_or_language_id
	 *
	 * @return string|null
	 * @throws \Exception
	 */
	public static function getDirectionClass($language_or_language_id): ?string
	{
		if(!$language_or_language_id){
			return NULL;
		}

		if(is_array($language_or_language_id)){
			$language = $language_or_language_id;
		}
		else {
			$language = \App\Common\SQL\Info\Info::getInstance()->getInfo("language", $language_or_language_id);
		}

		$class[] = strtolower("text-{$language['direction']}");
		$class[] = self::getScriptClass($language);

		return implode(" ", $class);
	}

	/**
	 * Returns either "ltr" or "rtl" based on the language ID passed.
	 *
	 * @param string|array|null $language_or_language_id
	 *
	 * @return string|null
	 * @throws \Exception
	 */
	public static function getDirection($language_or_language_id): ?string
	{
		if(!$language_or_language_id){
			return NULL;
		}

		if(is_array($language_or_language_id)){
			$language = $language_or_language_id;
		}
		else {
			$language = \App\Common\SQL\Info\Info::getInstance()->getInfo("language", $language_or_language_id);
		}

		return strtolower($language['direction']);
	}

	/**
	 * @param $language_or_language_id
	 *
	 * @return string|null
	 * @throws \Exception
	 */
	public static function getScriptClass($language_or_language_id): ?string
	{
		if(!$language_or_language_id){
			return NULL;
		}

		if(is_array($language_or_language_id)){
			$language = $language_or_language_id;
		}
		else {
			$language = \App\Common\SQL\Info\Info::getInstance()->getInfo("language", $language_or_language_id);
		}

		if(strtolower($language['script']) != "latin"){
			$class .= strtolower(" script-{$language['script']}");
		}

		return $class;
	}

	/**
	 * @param             $class
	 * @param string|null $language_id
	 * @param string|null $direction
	 *
	 * @return void
	 * @throws \Exception
	 */
	public static function setDirectionClass(&$class, ?string $language_id, ?string $direction = NULL): void
	{
		# Ensure the class is an array
		if(!is_array($class)){
			$class = [$class];
		}

		if($language_id){
			$class[] = self::getDirectionClass($language_id);
		}

		if($direction){
			$class[] = "text-{$direction}";
		}
	}

	/**
	 * Get the entire language table row array for a given language ID.
	 * If no language ID is provided, will return the default language.
	 *
	 * @param string|null $language_id
	 *
	 * @return array|null
	 * @throws \Exception
	 */
	public static function get(?string $language_id = NULL): ?array
	{
		$language_id = $language_id ?: self::DEFAULT_FROM_LANGUAGE_ID;
		return \App\Common\SQL\Info\Info::getInstance()->getInfo("language", $language_id);
	}

	public static function getAllLanguages(): array
	{
		return \App\Common\SQL\Info\Info::getInstance()->getInfo([
			"table" => "language",
			"order" => [
				"title" => "ASC",
			],
		]);
	}

	public function filter(array $a): bool
	{
		extract($a);

		# Get all languages
		$languages = self::getAllLanguages();

		if($vars['iso_639-1']){
			$languages = array_filter($languages, function($language) use ($vars){
				return $language['iso_639-1'] == $vars['iso_639-1'];
			});

			foreach($languages as $id => $language){
				if($language['language_id'] == $vars['iso_639-1']){
					$languages[$id]['selected'] = true;
				}
			}
		}

		if($vars['script']){
			$languages = array_filter($languages, function($language) use ($vars){
				return $language['script'] == $vars['script'];
			});
		}

		if($vars['country_code']){
			$country = $this->info([
				"columns" => [
					"languages",
				],
				"rel_table" => "country",
				"where" => [
					"country_code" => $vars['country_code'],
				],
				"limit" => 1,
			]);

			$languages = array_filter($languages, function($language) use ($country){
				return in_array($language['language_id'], explode(",", $country['languages']));
			});
		}

		$this->output->setOptions($this->getOptionsFromLanguages($languages, false, true, true));

		return true;
	}

	public static function getLanguagesFromCountryCode(string $country_code): array
	{
		$country = \App\Common\SQL\Info\Info::getInstance()->getInfo([
			"table" => "country",
			"where" => [
				"country_code" => strtolower($country_code),
			],
			"limit" => 1,
		]);

		$language_ids = explode(",", $country['languages']);

		$languages = \App\Common\SQL\Info\Info::getInstance()->getInfo([
			"rel_table" => "language",
			"where" => [
				["language_id", "IN", $language_ids],
			],
		]);

		# Determine the order by the $language_ids array
		$languages = array_map(function($language) use ($language_ids){
			$language['order'] = array_search($language['language_id'], $language_ids);
			return $language;
		}, $languages);

		usort($languages, function($a, $b){
			return $a['order'] <=> $b['order'];
		});

		return $languages;
	}

	public function getLanguagesFromCountry(array $a): bool
	{
		extract($a);

		if(!$vars["country_code"]){
			return true;
		}

		$languages = self::getLanguagesFromCountryCode($vars['country_code']);

		$this->output->setOptions($this->getOptionsFromLanguages($languages));

		return true;
	}

	public function getLanguagesFromScript(array $a): bool
	{
		extract($a);

		if(!$vars["script"]){
			return true;
		}

		$languages = $this->info([
			"rel_table" => "language",
			"where" => [
				"script" => $vars['script'],
			],
		]);

		$this->output->setOptions($this->getOptionsFromLanguages($languages));

		return true;
	}

	public function getLanguageAndLocale(array $a): bool
	{
		extract($a);

		if(!$vars["iso_639-1"]){
			return true;
		}

		$languages = $this->info([
			"rel_table" => "language",
			"where" => [
				"iso_639-1" => $vars['iso_639-1'],
			],
		]);

		$this->output->setOptions(Language::getOptionsFromLanguages($languages));

		return true;
	}

	/**
	 * @param string|array $language_or_id
	 * @param bool|null    $flag
	 * @param bool|null    $use_local_title
	 * @param bool|null    $badges
	 * @param bool|null    $include_tooltips
	 *
	 * @return string
	 * @throws \Exception
	 */
	public static function getTitle($language_or_id, ?bool $flag = NULL, ?bool $use_local_title = NULL, ?bool $badges = NULL, ?bool $include_tooltips = NULL): string
	{
		if(!is_array($language_or_id)){
			$language = \App\Common\SQL\Info\Info::getInstance()->getInfo("language", $language_or_id);
		}
		else {
			$language = $language_or_id;
		}

		# Flag
		if($flag){
			$title[] = Icon::generate(self::getLanguageIcon($language, $include_tooltips));
		}

		# Local title
		if($use_local_title && $language['local_title']){
			$title[] = $language['local_title'];
		}

		# English title
		else {
			$title[] = $language['title'];
		}

		# Badges
		if($badges){
			$badges = [];
			$badges[] = Badges::direction($language);
			$badges[] = Badges::script($language);
			$badges[] = Badges::languageId($language);

			$title[] = Badge::generate($badges);
		}

		return implode(" ", array_filter($title));
	}

	public static function getLanguageIcon(array $language, ?bool $internal = NULL): array
	{
		$style = [
			"margin-right" => "5px",
		];

		if($language['country_code']){
			return [
				"type" => "flag",
				"name" => strtolower($language['country_code']),
				"tooltip" => $internal ? "This language is primarily spoken in " . $language['country']['name'] . "." : NULL,
				"style" => $style,
			];
		}

		# Root languages only have two characters
		if(strlen($language['language_id']) == 2){
			// If the language ID is only two characters long

			if(!$internal){
				return [
					"name" => "globe",
					"style" => $style,
				];
			}

			# Get a count of the number of regional dialects that stem from this root language
			$count = Factory::getInstance()->select([
				"count" => true,
				"table" => "language",
				"where" => [
					"iso_639-1" => $language['language_id'],
					["language_id", "<>", $language['language_id']],
				],
			]);

			if($count){
				return [
					"name" => "sitemap",
					"tooltip" => "This is the root language for " . str::pluralise_if($count, "regional dialect", true) .
						" of the same language. Use this unless you specifically want a regional dialect.",
					"style" => $style,
				];
			}
		}

		$countries = \App\Common\SQL\Info\Info::getInstance()->getInfo([
			"rel_table" => "country",
			"where" => [
				"`country`.`languages` COLLATE utf8mb4_bin LIKE '%{$language['language_id']}%'",
			],
		]);

		if(count($countries) == 1){
			$country = reset($countries);
			return [
				"type" => "flag",
				"name" => strtolower($country['country_code']),
				"tooltip" => $internal ? "This language is primarily spoken in " . $country['name'] . "." : NULL,
				"style" => $style,
			];
		}

		foreach($countries as $country){
			$continents[$country['continent_name']]++;
		}
		asort($continents);

		$continent = array_key_last($continents);
		switch($continent) {
		case "Europe":
		case "Asia":
		case "Africa":
		case "Oceania":
			$icon = strtolower("earth-{$continent}");
			break;
		case "North America":
		case "South America":
			$icon = "earth-americas";
			break;
		case "Antarctica":
			$icon = "globe";
			break;
		}

		return [
			"name" => $icon,
			"tooltip" => $internal ? "This language is spoken in " . str::pluralise_if(count($countries), "country", true) . ", mostly in {$continent}." : NULL,
			"style" => $style,
		];
	}

	/**
	 * @param array     $languages An array of languages or language IDs
	 * @param bool|null $local_title
	 * @param bool|null $flag
	 * @param bool|null $badges
	 * @param bool|null $include_tooltips
	 *
	 * @return array
	 * @throws \Exception
	 */
	public static function getOptionsFromLanguages(array $languages, ?bool $local_title = NULL, ?bool $flag = NULL, ?bool $badges = NULL, ?bool $include_tooltips = NULL): ?array
	{
		$options = [];

		if(!$languages){
			return NULL;
		}

		# If the languages-array is a two-dimensional array where all the values are language IDs
		if(!is_array(reset($languages))){
			# Convert the array of IDS to an array of languages
			$languages = \App\Common\SQL\Info\Info::getInstance()->getInfo([
				"rel_table" => "language",
				"where" => [
					["language_id", "IN", $languages],
				],
			]);
		}


		foreach($languages as $language){
			$title = Language::getTitle($language, $flag, $local_title, $badges, $include_tooltips);

			$options[$language["language_id"]] = [
				"title" => strip_tags($title),
				"html" => $title,
				"selected" => $language["selected"],
				"alt" => $language['title'],
			];
		}

		return $options;
	}

	public static function getLocalLanguages(): array
	{
		global $user_id;
		$connection = Connection::get($user_id);
		$geolocation = Geolocation::get($connection['ip']);
		return Language::getLanguagesFromCountryCode($geolocation['country_code']);
	}

	/**
	 * A check to see if two languages are the same.
	 * By default, will only check the root language.
	 * If $root_is_same is set to false, will check the full language ID.
	 *
	 * @param string|null $language_id_a
	 * @param string|null $language_id_b
	 * @param bool|null   $root_is_same
	 *
	 * @return bool
	 */
	public static function areTheSame(?string $language_id_a, ?string $language_id_b, ?bool $root_is_same = true): bool
	{
		$language_id_a = strtolower($language_id_a);
		$language_id_b = strtolower($language_id_b);

		if($root_is_same){
			$language_id_a = substr($language_id_a, 0, 2);
			$language_id_b = substr($language_id_b, 0, 2);
		}

		return $language_id_a == $language_id_b;
	}
}