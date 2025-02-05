<?php

namespace App\Common\Language;

class Badges {
	public static function direction(array $language): array
	{
		$alt = "The language is written ";
		$alt .= $language['direction'] == "LTR" ? "left to right" : "right to left";

		return [
			"title" => $language['direction'],
			"basic" => true,
			"colour" => "secondary",
			"alt" => $alt
		];
	}
	public static function script(array $language): array
	{
		return [
			"title" => $language['script'],
			"basic" => true,
			"colour" => "primary",
			"alt" => "The script used to write the language is {$language['script']}"
		];
	}
	public static function languageId(array $language): array
	{
		switch(strlen($language['language_id'])){
		case 2:
			$alt = "The primary ISO 639-1 language subtag is {$language['language_id']}";
			break;
		case 5:
			$alt = "The primary ISO 639-1 language subtag is {$language['language_id']}";
			$alt .= " and the ISO 3166-1 alpha-2 country code is {$language['language_id']}";
			break;
		}
		return [
			"title" => $language['language_id'],
			"basic" => true,
			"colour" => "success",
			"class" => "text-monospace smallest",
			"alt" => $alt
		];
	}
}