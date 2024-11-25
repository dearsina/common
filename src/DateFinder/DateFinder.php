<?php


namespace App\Common\DateFinder;


use App\Common\str;

/**
 * Class DateFinder
 *
 * Takes most strings that are dates and extracts
 * the date portion and returns a DateTime object.
 *
 * Will translate non-English month names if the
 * month table is in place and populated.
 *
 * @package App\Common\DateFinder
 */
class DateFinder extends \App\Common\Prototype {

	const ARABIC_NUMERALS = [
		'٠' => '0',
		'١' => '1',
		'٢' => '2',
		'٣' => '3',
		'٤' => '4',
		'٥' => '5',
		'٦' => '6',
		'٧' => '7',
		'٨' => '8',
		'٩' => '9',
	];

	/**
	 * An array of month names
	 * in different languages.
	 *
	 * @var array|null
	 */
	private ?array $months = [];

	/**
	 * Cached translated date strings to make
	 * the date translation more efficient.
	 *
	 * @var array|null
	 */
	private ?array $translated_date_strings = [];

	public function __construct()
	{
		parent::__construct();

		# Load the months (in different languages) array
		$this->setMonths();
	}

	private function setMonths(): void
	{
		if(!$rows = $this->sql->select([
			"table" => "month",
			"include_removed" => true,
		])){
			return;
		}

		foreach($rows as $columns){
			foreach($columns as $key => $val){
				$this->months[$key][] = $val;
			}
		}
	}

	/**
	 * Handles Chinese dates and translates them to Y-m-d.
	 * Includes the Republic of China calendar.
	 *
	 * @param string $input_string
	 *
	 * @return void
	 * @link https://en.wikipedia.org/wiki/Republic_of_China_calendar
	 */
	private function translateChineseDate(string &$input_string): void
	{
		# Chinese date format: 112年12月31日
		$pattern = "/(\d{2,4})年(\d{1,2})月(\d{1,2})日/u";

		# If there is a match, convert the date to Y-m-d
		if(preg_match($pattern, $input_string, $matches)){
			# Ensure the month and day are two digits
			$matches[2] = str_pad($matches[2], 2, "0", STR_PAD_LEFT);
			$matches[3] = str_pad($matches[3], 2, "0", STR_PAD_LEFT);

			switch(strlen($matches[1])) {
			case 2:
				# If only two numbers (very uncharacteristic), let PHP handle it
				$input_string = $matches[3] . "-" . $matches[2] . "-" . $matches[1];
				break;
			case 3:
				# Account for RoC years
				$matches[1] += 1911;
			case 4:
				# Most common case
				$input_string = $matches[1] . "-" . $matches[2] . "-" . $matches[3];
				break;
			}
		}
	}

	private function translateHijriDateToGregorian(string &$date): void
	{
		if(!$dt = str::newDateTimeDateOnly($date)){
			return;
		}
		// Not a perfect way for those dates that couldn't be in the Gregorian calendar
		// Like 30 Feb

		# If the date is in the Hijri calendar
		if($dt->format("Y") > 1600){
			// Basically, the year in the Hijri calendar needs to boe less than 1600
			return;
		}

		$year = (int) $dt->format("Y");
		$month = (int)$dt->format("m");
		$day = (int)$dt->format("d");

		$jd = floor((11 * $year + 3) / 30) + floor(354 * $year) + floor(30 * $month)
			- floor(($month - 1) / 2) + $day + 1948440 - 386;

		$julian = $jd - 1721119;
		$calc1 = 4 * $julian - 1;
		$year = floor($calc1 / 146097);
		$julian = floor($calc1 - 146097 * $year);
		$day = floor($julian / 4);
		$calc2 = 4 * $day + 3;
		$julian = floor($calc2 / 1461);
		$day = $calc2 - 1461 * $julian;
		$day = floor(($day + 4) / 4);
		$calc3 = 5 * $day - 3;
		$month = floor($calc3 / 153);
		$day = $calc3 - 153 * $month;
		$day = floor(($day + 5) / 5);
		$year = 100 * $year + $julian;

		if ($month < 10) {
			$month = $month + 3;
		}

		else {
			$month = $month - 9;
			$year = $year + 1;
		}

		$gdt = str::newDateTimeDateOnly("{$year}-{$month}-{$day}");
		$date = $gdt->format("Y-m-d");
	}

	/**
	 * If a date has the month written in full, will translate
	 * the date to English. Will also filter away non-date characters
	 * from the string to make it easier for the machine to
	 * interpret the string as a date.
	 *
	 * @param string $string
	 */
	private function translateDate(string &$input_string): void
	{
		# If the string has already been translated
		if($output_string = $this->translated_date_strings[$input_string]){
			$input_string = $output_string;
			return;
		}

		# Split words up
		$words = preg_split("/([^\w])/", $input_string, -1, PREG_SPLIT_DELIM_CAPTURE);

		if(count($words) >= 5){
			//if the string is at least three words (and two delimiters), (presumably) day month year

			foreach($words as $number => $word){
				//for each of the three words

				# Skip the odd numbers
				if(str::isOdd($number)){
					continue;
				}

				foreach($this->months as $month => $languages){
					//for each of the months
					if(in_array($word, $languages)){
						//if the word is matched

						# Replace the word with the english translation
						$words[$number] = $month;

						# A month name won't appear twice in a date string, we're done for now
						break 2;
					}
				}
			}
		}

		$output_string = implode("", $words);

		# Store a copy
		$this->translated_date_strings[$input_string] = $output_string;

		# Change the input string (that was passed by reference) to the output string
		$input_string = $output_string;
	}

	/**
	 * Date strings are never more than 8 numbers.
	 * But date-time strings could be up to 14.
	 * Will return TRUE if the string has more than
	 * 14 numbers.
	 *
	 * @param string $string
	 *
	 * @return bool
	 */
	private function hasTooManyNumbers(string $string): bool
	{
		return strlen(preg_replace("/[^0-9]/", "", $string)) > 14;
	}

	const COMPLEX_DATE_PATTERNS = [
		# Change 2023 Nov(ember) 06 to 06-Nov(ember)-2023
		"/(\d{4})\s+((?:Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|June?|July?|Aug(?:ust)?|Sep(?:t(?:ember)?)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?))\s+(\d{1,2})/" => "\$3-\$2-\$1",

		# Change 06 Nov(ember) 2023 to 06-Nov(ember)-2023
		"/.*(\d{2})\s+((?:Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|June?|July?|Aug(?:ust)?|Sep(?:t(?:ember)?)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?))\s+(\d{2})/" => "\$1-\$2-\$3",

		# Changing YYYY/MM/DD to YYYY-MM-DD
		"/.*(\d{4})\/(\d{2})\/(\d{2}).*/" => "\$1-\$2-\$3",

		# Changing DD/MM/YYYY to DD-MM-YYYY (ignoring US dates)
		"/.*(\d{2})\/(\d{2})\/(\d{4}).*/" => "\$1-\$2-\$3",

		# Simplify strings that contain long form narrative dates
		"/.*\b(\d{1,2})(?:(?:st)|(?:nd)|(?:rd)|(?:th))?(?:\s+|(?: of ))?((?:Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|June?|July?|Aug(?:ust)?|Sep(?:t(?:ember)?)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?))\s+(\d{4})\b.*/i" => "\$1-\$2-\$3",
	];

	/**
	 * Change YYYY/MM/DD to YYYY-MM-DD
	 *
	 * The following date changes ignore US dates
	 * Change DD/MM/YYYY to DD-MM-YYYY
	 * Change DD.MM.YY to DD-MM-YYYY (split at this year's number)
	 * Change DD-MM-YY to DD-MM-YYYY (split at this year's number)
	 * Change DD/MM/YY to DD-MM-YYYY (split at this year's number)
	 *
	 * Change DDth of MMM YYYY to DD MMM YYYY
	 *
	 * The only shortcoming with this approach is that it doesn't
	 * take into consideration that there may be more than one
	 * date in the string.
	 *
	 * @param $string
	 */
	private function changeCommonChallengingFormats(&$string): void
	{
		# Simplify complex date formats
		foreach(self::COMPLEX_DATE_PATTERNS as $pattern => $replacement){
			$string = preg_replace($pattern, $replacement, $string);
		}

		# Changing DD.MM.YY and DD-MM-YY and DD/MM/YY to YYYY-MM-DD (ignoring US dates)
		if(preg_match("/^(\d{2})(?:\.|\-|\/)(\d{2})(?:\.|\-|\/)(\d{2})$/", $string, $matches)){
			$year = ($matches[3] > date("y") ? "19" : "20") . $matches[3];
			$month = $matches[2];
			$date = $matches[1];
			$string = "{$year}-{$month}-{$date}";
		}
	}

	private function handleStringWithoutAlpha(string $string): ?\DateTime
	{
		# Break the string into the component number parts
		$digits = preg_split("/[^\d]/", $string);

		# There must be at least 3 component number parts
		if(count($digits) < 3){
			return NULL;
		}

		# Each part must be between 2 and 4 digits
		foreach($digits as $digit){
			if(strlen($digit) > 4 || strlen($digit) < 2){
				return NULL;
			}
		}

		# You can't write a date with less than 4 digits (at the VERY least)
		if(strlen(implode("", $digits)) < 4){
			return NULL;
		}

		# Finally, if the format is acceptable, use strtotime to get the unix time
		if(($unixtime = strtotime($string)) !== false){
			//if the unix time is valid

			# Generate the DateTime object
			$date = new \DateTime();
			$date->setTimestamp($unixtime);

			# Return it
			return $date;
		}

		return NULL;
	}

	private function handleStringWithAlpha(string $string): ?\DateTime
	{
		# Trim away non-date alphanumeric, non date-y characters to make it easier to discern if the string is a date
		$string = preg_replace("/[^\s\-\.\/0-9A-Z:]/i", "", $string);

		# Make sure the remaining string corresponds to minimum requirements
		if(strlen($string) < 6){
			//The very shortest date can be written as 1-1-11, minimum 6 characters)
			return NULL;
		}

		# Strings with alpha work well with date_parse
		$date = date_parse($string);
		if($date['error_count'] == 0 && $date['warning_count'] == 0){
			if(checkdate($date['month'], $date['day'], $date['year'])){
				return new \DateTime("{$date['year']}-{$date['month']}-{$date['day']}");
			}
		}

		return NULL;
	}

	/**
	 * Identifies if a string is in fact, a date.
	 *
	 * @param string|null $string $string
	 *
	 * @return \DateTime|null Returns an object or NULL if the string is not a date.
	 */
	public function isADate(?string $string): ?\DateTime
	{$original_string = $string;
		# Ensure string is long enough to be a date
		if(strlen($string) < 6){
			//Date string needs to be at least 6 characters (YYMMDD), all in
			return NULL;
		}

		# Convert Arabic numerals to English numerals
		$this->convertArabicNumeralsToLatin($string);

		# A date must have at least one number
		if(!preg_match("/\d/", $string)){
			return NULL;
		}

		# Translate Chinese dates to Y-m-d
		$this->translateChineseDate($string);

		$this->translateHijriDateToGregorian($string);

		# Translate the date to English (if it's in a non-English language)
		$this->translateDate($string);

		# Filter out the "Narrative:Date" strings
		$sections = preg_split("/\s*:\s*/", $string);
		if(count($sections) == 2){
			foreach($sections as $section){
				if($dt = $this->isADate($section)){
					return $dt;
				}
			}
			return NULL;
		}

		# Even a date time string has only 14 numbers; more and it's not a date
		if($this->hasTooManyNumbers($string)){
			return NULL;
		}

		$this->changeCommonChallengingFormats($string);

		# If the string contains at least one letter
		if(preg_match("/[A-Z]/i", $string)){
			return $this->handleStringWithAlpha($string);
		}

		# If the string has NO letters
		return $this->handleStringWithoutAlpha($string);
	}

	private function convertArabicNumeralsToLatin(string &$string): void
	{
		$string = strtr($string, self::ARABIC_NUMERALS);
	}
}