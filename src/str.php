<?php

namespace App\Common;

use App\Email\Email;
use App\UI\Badge;
use App\UI\Icon;
use GuzzleHttp\Client;

/**
 * Static class related mainly to string manipulation.
 * <code>
 * str::
 * </code>
 * or
 * <code>
 * $this->str->
 * </code>
 * @package App\Common
 */
class str {
	/**
	 * The below code is just here so that str can be used in HEREDOCs by writing
	 * $this->str->method() instead of str::method(), which wont work.
	 * Can now be pulled in locally so that $this->str->method() can be used.
	 * @link https://stackoverflow.com/questions/8773236/proper-way-to-access-a-static-variable-inside-a-string-with-a-heredoc-syntax/
	 * @var bool
	 */
	private $str;
	private static $instance = NULL;

	/**
	 * The constructor is private so that the class
	 * can be run in static mode.
	 * Cloning and wakeup are also set to private to prevent
	 * cloning and unserialising of the Hash() object.
	 */
	private function __construct()
	{
		$this->str = false;
	}

	/**
	 * Given a dot notation key, this function will return the value from the array.
	 *
	 * @param array  $array
	 * @param string $dot_notation_key
	 *
	 * @return mixed|null
	 */
	public static function getArrayValueByDotNotation(array $array, string $dot_notation_key)
	{
		// Break the dot notation key into an array of keys
		$keys = explode('.', $dot_notation_key);

		// Start at the root of the array
		$current = &$array;

		// Traverse or create sub-arrays
		foreach($keys as $key){
			// If the sub-key doesn't exist or isn't an array, initialize it
			if(!isset($current[$key])){
				return NULL;
			}

			// Move $current to the next level by reference
			$current = &$current[$key];
		}

		// Finally, get the value
		return $current;
	}

	/**
	 * Recursively sets a value in an array using dot notation.
	 *
	 * @param array  $array  The array you want to update (passed by reference)
	 * @param string $dotKey A string using dot notation, e.g. "foo.bar.baz"
	 * @param mixed  $value  The value to set at that key
	 *
	 * @link https://chatgpt.com/share/678ccd08-2af8-8006-95da-aadcc23def31
	 */
	public static function setArrayValueByDotNotation(array &$array, string $dot_key, $value): void
	{
		// Break the dot_key into an array of keys
		$keys = explode('.', $dot_key);

		// Start at the root of the array
		$current = &$array;

		// Traverse or create sub-arrays
		foreach($keys as $key){
			// If the sub-key doesn't exist or isn't an array, initialize it
			if(!isset($current[$key]) || !is_array($current[$key])){
				$current[$key] = [];
			}

			// Move $current to the next level by reference
			$current = &$current[$key];
		}

		// Finally, set the value
		$current = $value;
	}

	private function __clone()
	{
	}

	private function __wakeup()
	{
	}

	/**
	 * @return str|null
	 */
	public static function getInstance()
	{

		// Check if instance is already exists
		if(self::$instance == NULL){
			self::$instance = new str();
		}

		return self::$instance;
	}

	/**
	 * Defines the minimum password length requirements
	 */
	const MIMINUM_PASSWORD_LENGTH = 8;

	/**
	 * Defines the minimum phone number length requirement.
	 */
	const MINIMUM_PHONE_NUMBER_LENGTH = 5;

	/**
	 * PHP7 version of PHP8's str_ends_with() method.
	 *
	 * @param string $haystack
	 * @param string $needle
	 *
	 * @return bool
	 */
	public static function endsWith(string $haystack, string $needle): bool
	{
		$needle_len = strlen($needle);
		return ($needle_len === 0 || 0 === substr_compare($haystack, $needle, -$needle_len));
	}

	/**
	 * DEPRECIATED, USE capitalise() INSTEAD
	 *
	 * Standardizes the capitalization on people's names and the titles of reports and essays.
	 * You may need to adapt the lists in "$all_uppercase" and "$all_lowercase" to suit the data that you are working
	 * with.
	 *
	 * @param      $str
	 * @param bool $is_name
	 * @param bool $all_words
	 *
	 * @return mixed
	 * @link http://www.php.net/manual/en/function.ucwords.php#60064
	 */
	/*static function capitalise_name($str, $is_name = false, $all_words = false)
	{
		if(is_array($str)){
			return $str;
			//TODO Fix this
		}
		// exceptions to standard case conversion
		if($is_name){
			$all_uppercase = 'MD';
			$all_lowercase = 'De La|De Las|Der|Van De|Van Der|Vit De|Von|Or|And|D|Del';
			$str = preg_replace_callback("/\\b(\\w)/u", function($matches){
				return strtoupper($matches[1]);
			}, mb_strtolower(trim($str)));
		}
		else if(!$all_words){
			//if only the first word is to be capitalised
			$str_array = explode(" ", $str);
			$first_word = array_shift($str_array);
			$str = str::mb_ucfirst(mb_strtolower(trim($str)));
			if(is_array(self::ALL_UPPERCASE)){
				$all_uppercase = '';
				foreach(self::ALL_UPPERCASE as $uc){
					if($first_word == mb_strtolower($uc)){
						$str = strtoupper($first_word) . " " . implode(" ", $str_array);
					}
					$all_uppercase .= mb_strtolower($uc) . '|';
					//set them all to lowercase
				}
			}
			if(is_array(self::ALL_LOWERCASE)){
				$all_lowercase = '';
				foreach(self::ALL_LOWERCASE as $uc){
					if($first_word == mb_strtolower($uc)){
						$str = mb_strtolower($first_word) . " " . implode(" ", $str_array);
					}
					$all_lowercase .= mb_strtolower($uc) . '|';
					//set them all to lowercase
				}
			}
		}
		else {
			// addresses, essay titles ... and anything else
			if(is_array(self::ALL_UPPERCASE)){
				foreach(self::ALL_UPPERCASE as $uc){
					$all_uppercase .= str::mb_ucfirst(mb_strtolower($uc)) . '|';
				}
			}
			if(is_array(self::ALL_LOWERCASE)){
				foreach(self::ALL_LOWERCASE as $uc){
					$all_lowercase .= str::mb_ucfirst($uc) . '|';
				}
			}
			// captialize all first letters
			//$str = preg_replace('/\\b(\\w)/e', 'strtoupper("$1")', mb_strtolower(trim($str)));
			$str = preg_replace_callback("/\\b(\\w)/u", function($matches){
				return strtoupper($matches[1]);
			}, mb_strtolower(trim($str)));
		}
		# Defaults
		$prefixes = "Mc";
		$suffixes = "'S";

		if($all_uppercase){
			// capitalize acronymns and initialisms e.g. PHP
			//$str = preg_replace("/\\b($all_uppercase)\\b/e", 'strtoupper("$1")', $str);
			$str = preg_replace_callback("/\\b($all_uppercase)\\b/u", function($matches){
				return strtoupper($matches[1]);
			}, $str);
		}
		if($all_lowercase){
			// decapitalize short words e.g. and
			if($is_name){
				// all occurences will be changed to lowercase
				//$str = preg_replace("/\\b($all_lowercase)\\b/e", 'mb_strtolower("$1")', $str);
				$str = preg_replace_callback("/\\b($all_lowercase)\\b/u", function($matches){
					return mb_strtolower($matches[1]);
				}, $str);
			}
			else {
				// first and last word will not be changed to lower case (i.e. titles)
				//$str = preg_replace("/(?<=\\W)($all_lowercase)(?=\\W)/e", 'mb_strtolower("$1")', $str);
				$str = preg_replace_callback("/(?<=\\W)($all_lowercase)(?=\\W)/u", function($matches){
					return mb_strtolower($matches[1]);
				}, $str);
			}
		}
		if($prefixes){
			// capitalize letter after certain name prefixes e.g 'Mc'
			//$str = preg_replace("/\\b($prefixes)(\\w)/e", '"$1".strtoupper("$2")', $str);
			$str = preg_replace_callback("/\\b($prefixes)(\\w)/u", function($matches){
				return "${matches[1]}" . strtoupper($matches[2]);
			}, $str);
		}
		if($suffixes){
			// decapitalize certain word suffixes e.g. 's
			//$str = preg_replace("/(\\w)($suffixes)\\b/e", '"$1".mb_strtolower("$2")', $str);
			$str = preg_replace_callback("/(\\w)($suffixes)\\b/u", function($matches){
				return "${matches[1]}" . mb_strtolower($matches[2]);
			}, $str);
		}
		return $str;
	}*/

	/**
	 * Fixes the casing on all titles
	 * <code>
	 * str::title("str");
	 * str::title("name",true);
	 * str::title("not only first word",false,true);
	 * </code>
	 *
	 * @param string|null $string
	 * @param bool|null   $capitalise_all_words If set to TRUE will capitalise all words, if set to FALSE, will not
	 *                                          capitalise any words
	 *
	 * @return string|null
	 */
	static function title(?string $string, ?bool $capitalise_all_words = NULL): ?string
	{
		$string = str_replace("doc_", "document_", $string);
		$string = preg_replace("/_col\b/i", "_column", $string);
		$string = str_replace("_", " ", $string);

		if($capitalise_all_words === false){
			return $string;
		}

		if($capitalise_all_words){
			return self::capitalise($string);
		}

		# If only the first word is to be capitalised
		$str_array = explode(" ", $string);
		$first_word = array_shift($str_array);
		$str = str::mb_ucfirst(mb_strtolower(trim($string)));

		if(is_array(self::ALL_UPPERCASE)){
			// Capitalize acronyms and initials, e.g. PHP
			$all_uppercase = '';
			foreach(self::ALL_UPPERCASE as $uc){
				if($first_word == mb_strtolower($uc)){
					$str = strtoupper($first_word) . " " . implode(" ", $str_array);
				}
				$all_uppercase .= mb_strtolower($uc) . '|';
				//set them all to lowercase
			}

			//$str = preg_replace("/\\b($all_uppercase)\\b/e", 'strtoupper("$1")', $str);
			$str = preg_replace_callback("/\\b($all_uppercase)\\b/u", function($matches){
				return strtoupper($matches[1]);
			}, $str);
		}

		if(is_array(self::ALL_LOWERCASE)){
			// Remove capitalisation from short words, e.g. "and"
			$all_lowercase = '';
			foreach(self::ALL_LOWERCASE as $uc){
				if($first_word == mb_strtolower($uc)){
					$str = mb_strtolower($first_word) . " " . implode(" ", $str_array);
				}
				$all_lowercase .= mb_strtolower($uc) . '|';
				//set them all to lowercase
			}

			// The first and last word will not be changed to lowercase (i.e. titles)
			$str = preg_replace_callback("/(?<=\\W)($all_lowercase)(?=\\W)/u", function($matches){
				return mb_strtolower($matches[1]);
			}, $str);

		}
		if(is_array(self::NAME_PREFIXES)){
			// Capitalize letter after certain name prefixes e.g 'Mc'
			foreach(self::NAME_PREFIXES as $prefix){
				$str = preg_replace_callback("/\\b($prefix)(\\w)/u", function($matches){
					return "{$matches[1]}" . strtoupper($matches[2]);
				}, $str);
			}
		}

		if(is_array(self::NAME_SUFFIXES)){
			// Remove capitalisation from certain word suffixes, e.g. 's
			foreach(self::NAME_SUFFIXES as $suffix){
				$str = preg_replace_callback("/(\\w)($suffix)\\b/u", function($matches){
					return "{$matches[1]}" . mb_strtolower($matches[2]);
				}, $str);
			}
		}
		return $str;
	}

	/**
	 * Words or abbreviations that should always be all uppercase
	 */
	const ALL_UPPERCASE = [
		"KYC",
		"UK",
		"VAT",
		"ID",
		"AI",
		"URL",
	];

	const NAME_PREFIXES = [
		"Mc",
	];

	const NAME_SUFFIXES = [
		"'S",
	];

	/**
	 * Words or abbreviations that should always be all lowercase
	 */
	const ALL_LOWERCASE = [
		//		"a",
		//In a title, a shouldn't be capitalised, but in name (initial), it should be
		"and",
		"as",
		"by",
		"in",
		"of",
		"or",
		"to",
	];

	/**
	 * Words that need to be exempt from the
	 * upper/lowercase logic.
	 */
	const ALL_CAPITALISED = [
		"Rd",
		"Blvd",
		"Pty",
		"Ltd",
	];

	/**
	 * Honorifics that only contain vowels.
	 *
	 */
	const CONSONANT_ONLY_HONORIFICS = [
		# English
		"Mr",
		"Mrs",
		"Ms",
		"Dr",
		"Br",
		"Sr",
		"Fr",
		"Pr",
		"St",

		# Afrikaans
		"Mnr",
	];

	/**
	 * Surname prefixes that should be lowercase,
	 * unless not following another word (firstname).
	 */
	const SURNAME_PREFIXES = [
		"de la",
		"de las",
		"van de",
		"van der",
		"vit de",
		"von",
		"van",
		"del",
		"der",
		"du",
	];

	/**
	 * Capitalises every (appropriate) word in a given string.
	 *
	 * @author  https://stackoverflow.com/users/429071/dearsina
	 * @version 1.0
	 *
	 * @param string|null $string
	 *
	 * @return string|null
	 */
	public static function capitalise(?string $string): ?string
	{
		if(!$string){
			return $string;
		}

		# Strip away multi-spaces
		$string = preg_replace("/\s{2,}/", " ", $string);

		# Ensure there is always a space after a comma
		$string = preg_replace("/,([^\s])/", ", $1", $string);

		# A word is anything separated by spaces or a dash
		$string = preg_replace_callback("/([^\s\-\.\(\)]+)/u", function($matches){
			# Make the word lowercase
			$word = mb_strtolower($matches[1]);

			# If the word needs to be all lowercase
			if(in_array($word, self::ALL_LOWERCASE)){
				return strtolower($word);
			}

			# If the word needs to be all uppercase
			if(in_array(mb_strtoupper($word), self::ALL_UPPERCASE)){
				return strtoupper($word);
			}

			# If the word needs to be CapitalCase
			if(in_array(ucwords($word), self::ALL_CAPITALISED)){
				return ucwords($word);
			}

			# Create a version without diacritics
			$transliterator = \Transliterator::createFromRules(':: Any-Latin; :: Latin-ASCII; :: NFD; :: [:Nonspacing Mark:] Remove; :: Lower(); :: NFC;', \Transliterator::FORWARD);
			$ascii_word = $transliterator->transliterate($word);


			# If the word contains non-alpha characters (numbers, &, etc), with exceptions (comma, '), assume it's an abbreviation
			if(preg_match("/[^a-z,']/i", $ascii_word)){
				return strtoupper($word);
			}

			# If the word doesn't contain any vowels, assume it's an abbreviation
			if(!preg_match("/[aeiouy]/i", $ascii_word)){
				# Unless the word is an honorific
				if(!in_array(str::mb_ucfirst($word), self::CONSONANT_ONLY_HONORIFICS)){
					return strtoupper($word);
				}
			}

			# If the word contains two of the same vowel and is 3 characters or fewer, assume it's an abbreviation
			if(strlen($word) <= 3 && preg_match("/([aeiouy])\1/", $word)){
				return strtoupper($word);
			}

			# Ensure O'Connor, L'Oreal, etc, are double capitalised, with exceptions (d')
			if(preg_match("/\b([a-z]')(\p{L}+)\b/ui", $word, $match)){
				# Some prefixes (like d') are not capitalised
				if(in_array($match[1], ["d'"])){
					return $match[1] . str::mb_ucfirst($match[2]);
				}

				# Otherwise, everything is capitalised
				return strtoupper($match[1]) . str::mb_ucfirst($match[2]);
			}

			# Otherwise, return the word with the first letter (only) capitalised
			return str::mb_ucfirst($word);
			//The most common outcome
		}, $string);

		# Cater for the Mc prefix
		$pattern = "/(Mc)([b-df-hj-np-tv-z])/";
		//Mc followed by a consonant
		$string = preg_replace_callback($pattern, function($matches){
			return "Mc" . str::mb_ucfirst($matches[2]);
		}, $string);

		# Cater for Roman numerals (need to be in all caps)
		$pattern = "/\b((?<![MDCLXVI])(?=[MDCLXVI])M{0,3}(?:C[MD]|D?C{0,3})(?:X[CL]|L?X{0,3})(?:I[XV]|V?I{0,3}))\b/ui";
		$string = preg_replace_callback($pattern, function($matches){
			return strtoupper($matches[1]);
		}, $string);

		# Cater for surname prefixes (must be after the Roman numerals)
		$pattern = "/\b (" . implode("|", self::SURNAME_PREFIXES) . ") \b/ui";
		//A surname prefix, bookended by words
		$string = preg_replace_callback($pattern, function($matches){
			return strtolower(" {$matches[1]} ");
		}, $string);

		# Cater for ordinal numbers
		$pattern = "/\b(\d+(?:st|nd|rd|th))\b/ui";
		//A number suffixed with an ordinal
		$string = preg_replace_callback($pattern, function($matches){
			return strtolower($matches[1]);
		}, $string);

		# And we're done
		return $string;
	}

	/**
	 * Multi-byte `ucfirst` method.
	 *
	 * @param string|null $string
	 *
	 * @return string|null
	 * @link https://stackoverflow.com/a/14161325/429071
	 */
	public static function mb_ucfirst(?string $string): ?string
	{
		if(!$string){
			return $string;
		}

		return mb_strtoupper(mb_substr($string, 0, 1)) . mb_strtolower(mb_substr($string, 1));
	}

	/**
	 * Given a title string, will suffix with (n), where "n" is an incremental
	 * number. Will not suffix (n) if an (n) already exists, instead it will
	 * increase n by 1.
	 *
	 * @param string $title
	 */
	public static function copyTitle(?string &$title): void
	{
		if(!$title){
			return;
		}

		$pattern = '/^(.+?)\((\d+)\)$/';
		if(preg_match($pattern, $title, $matches)){
			$digit = $matches[2] + 1;
			$suffix = "({$digit})";
			$title = preg_replace($pattern, '$1' . $suffix, $title);
		}
		else {
			$title .= " (1)";
		}
	}

	/**
	 * Takes a float or decimal and converts it to a percentage string,
	 * suffixed with the % sign.
	 *
	 * @param     $int_fraction float
	 * @param int $decimals     int The number of decimal points to include
	 *
	 * @return string
	 */
	static function percent(?float $int_fraction, ?int $decimals = 0)
	{
		$int = round($int_fraction * 100, $decimals);
		return "{$int}%";
	}

	/**
	 * Given a set of keys, traverses the array,
	 * looks for those keys, and if they're orphans,
	 * flattens them.
	 * By flatten is meant to remove the numerical level of a child array:
	 * <code>
	 * $array['parent'][0]['child'] > $array['parent']['child']
	 * </code>
	 *
	 * @param array $array An array potentially containing numerical array children.
	 * @param array $keys  A list of keys, if children are orphaned numerical arrays, to be flattened.
	 *
	 * @return array
	 */
	public static function flattenSingleChildren(?array &$array, array $keys): void
	{
		if(!is_array($array)){
			return;
		}

		foreach($array as $key => $val){
			# We're not interested in non-array values
			if(!is_array($val)){
				continue;
			}

			# Flatten keys in scope (that only have 1 child anyway)
			if(str::isNumericArray($val) && in_array($key, $keys) && count($val) == 1){
				$array[$key] = reset($val);
			}

			# This can happen if an array within an array is just a string
			if(!is_array($array[$key])){
				continue;
			}

			# Go deeper
			str::flattenSingleChildren($array[$key], $keys);
		}
	}

	/**
	 * Takes an array or a string and converts it into a base64 URL safe string.
	 *
	 * @param array|string|null $input
	 *
	 * @return string
	 */
	public static function base64_encode_url($input = NULL): ?string
	{
		if(!$input){
			return $input;
		}

		if(is_array($input)){
			$input = json_encode($input);
		}

		return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($input));
	}

	/**
	 * Given a base64 URl string, will decode the string and return it,
	 * or convert it back to an array if the decoded string turns out to be JSON.
	 *
	 * @param $string
	 *
	 * @return mixed
	 */
	public static function base64_decode_url(?string $string)
	{
		$string = base64_decode(str_replace(['-', '_'], ['+', '/'], $string));

		if(str::isJson($string)){
			$string = json_decode($string, true);
		}

		return $string;
	}

	/**
	 * Simple array tree builder from a flat array where every row, bar the master
	 * has a parent ID column populated.
	 *
	 * @param array       $elements
	 * @param string      $id_col_name
	 * @param string      $parent_id_col_name
	 * @param string|null $parentId
	 *
	 * @return array
	 */
	public static function buildTree(array $elements, string $id_col_name, string $parent_id_col_name, ?string $parentId = NULL): array
	{
		$branch = [];

		foreach($elements as $element){
			if($element[$parent_id_col_name] == $parentId){
				$children = self::buildTree($elements, $id_col_name, $parent_id_col_name, $element[$id_col_name]);
				if($children){
					$element['children'] = $children;
				}
				$branch[] = $element;
			}
		}

		return $branch;
	}

	/**
	 * Returns a readable method backtrace, like so:
	 * <code>
	 * [3176] whitelabel.php->load_ajax_call([{}]);
	 * [  28] error_log.php->unresolved([{}]);
	 * </code>
	 * Just by running this line at whichever point the backtrace is required
	 * <code>str::backtrace();</code>
	 *
	 * @param null $return
	 *
	 * @return string
	 */
	static function backtrace(?bool $return = false, ?bool $keep_arguments = true)
	{
		$steps = [];
		array_walk(debug_backtrace(), function($a) use (&$steps, $keep_arguments){
			$args = $keep_arguments ? json_encode($a['args']) : NULL;
			$line_number = str_pad($a['line'], 4, " ", STR_PAD_LEFT);
			$file_name = basename($a['file']);
			$steps[] = "{$a['function']}({$args});\r\n[{$line_number}] {$file_name}->";
		});

		# Fix is so that the functions and filenames are aligned correctly
		$steps_string = implode("", $steps);
		$correctly_aligned_steps = array_reverse(explode("\r\n", $steps_string));

		# Remove the first step, which is always the string ajax.php
		array_shift($correctly_aligned_steps);
		# Remove the last step, which is just the string "backtrace()"
		array_pop($correctly_aligned_steps);

		$steps = implode("\r\n", $correctly_aligned_steps);

		# If we're running in dev, grab any potential backtraces from the parent thread also
		if(str::isDev()){
			global $backtrace;
			if($backtrace){
					$steps = base64_decode($backtrace) . PHP_EOL . $steps;
			}
		}

		if($return){
			return $steps . PHP_EOL;
		}

		print $steps;
		exit;
	}

	public static function isOdd(?int $number): bool
	{
		return $number % 2 != 0;
	}

	public static function isEven(?int $number): bool
	{
		return $number % 2 == 0;
	}

	/**
	 * Checks to see if the string is a date in the given format.
	 *
	 * @param string|null $date
	 * @param string|null $format
	 *
	 * @return bool
	 */
	public static function isDate(?string $date, ?string $format = "Y-m-d"): bool
	{
		$dt = \DateTime::createFromFormat($format, $date);
		return $dt !== false && !array_sum($dt::getLastErrors());
	}

	/**
	 * Returns true if we're NOT in a production environment.
	 *
	 * When this method is called by a cron job, the $_SERVER
	 * array is not set, and even when it is set, it's not the
	 * most reliable way to determine the environment.
	 *
	 * Thus we will only rely on it if the value of the SERVER_ADDR
	 * is one of the expected IP addresses. Otherwise we will
	 * use the `ip a` results.
	 *
	 * @return bool
	 */
	public static function isDev(): bool
	{
		# Get all the production IP addresses
		$prod_ips = array_map(function($ip){
			return trim($ip);
		}, explode(",", $_ENV['pro_ip']));

		# Get all the development IP addresses
		$dev_ips = array_map(function($ip){
			return trim($ip);
		}, explode(",", $_ENV['dev_ip']));

		# Use the SERVER_ADDR if it's set
		if($_SERVER['SERVER_ADDR']){
			# But only if it's one of the expected DEV or PROD IPs
			if(in_array($_SERVER['SERVER_ADDR'], array_merge(
				$prod_ips,
				$dev_ips
			))){
				# Finally, we check if the server address is in the production IPs
				return !in_array($_SERVER['SERVER_ADDR'], $prod_ips);
				// If it's not, we're in DEV
			}
			// Otherwise, we're going to use the `ip a` results
		}

		# Otherwise, check the `ip a` results for a match
		return !array_intersect($prod_ips, str::getLocalServerIPs());
		// If there is no overlap between the two arrays, then we're in dev
	}

	/**
	 * Returns an array with all the local server addresses displayed
	 * when running the *NIX command `ip a`.
	 *
	 * Running "ip a" because it's more reliable than `ifconfig`, which sometimes
	 * throws a `sh: 1: ifconfig: not found` error message
	 *
	 * @param bool|null $withV6 Include IPv6 addresses (default is TRUE)
	 *
	 * @return array
	 */
	public static function getLocalServerIPs(?bool $withV6 = true): array
	{
		preg_match_all('/inet' . ($withV6 ? '6?' : '') . ' ([^ \/]+)/', `ip a`, $ips);
		return $ips[1];
	}

	/**
	 * Returns TRUE if the script is run from the command line (`cli`),
	 * or false if it's run from a browser (`http` or `apache2handler`).
	 *
	 *
	 * @param bool|null $or_die If set to TRUE, will kill the script execution if it's NOT run from CLI.
	 *
	 * @return bool
	 */
	static function runFromCLI(?bool $or_die = NULL): bool
	{
		if($or_die){
			str::runFromCLI() or die("This method can only be accessed from the command line.");
			return true;
		}
		return (PHP_SAPI == "cli");
	}

	/**
	 * Check if this is a headless chicken, I mean chrome.
	 *
	 * @return bool
	 */
	static function runHeadlessChrome(): bool
	{
		$headers = getallheaders();
		return $headers['User-Agent'] == $_ENV['db_password'];
	}

	/**
	 * Returns TRUE if this is an API call.
	 *
	 * @param array $a
	 *
	 * @return bool
	 */
	public static function isApiCall(array $a): bool
	{
		return key_exists("sandbox", $a);
	}

	/**
	 * For external files, will check the file header,
	 * for internal files, will only check the file
	 * extension.
	 *
	 * @param string $path
	 *
	 * @return bool
	 * @link https://stackoverflow.com/a/10494842/429071
	 */
	public static function isSvg(string $path): bool
	{
		if(strpos($path, "://") === false){
			//if local file

			# A svg suffix is sufficient if the file is local
			return substr($path, -3) == "svg";
		}

		# Otherwise, check the headers
		if(!$headers = @get_headers($path)){
			return false;
		}

		# Ensure headers extracted successfully
		if(!is_array($headers)){
			return false;
		}

		return in_array("Content-Type: image/svg+xml", $headers);
	}

	/**
	 * Returns the domain, suffixed with /.
	 * To be used as the basis for the URL for any (internal) link:
	 * <code>
	 * $url = $this->getDomain();
	 * </code>
	 *
	 * When a template is generated via CLI, the server HTTP HOST value is not populated.
	 *
	 * @param string|null $subdomain If a custom subdomain is to be used.
	 *
	 * @return string
	 */
	public static function getDomain(?string $subdomain = NULL): string
	{
		if($subdomain){
			return "https://{$subdomain}.{$_ENV['domain']}/";
		}
		else if($_SERVER['HTTP_HOST']){
			return "https://{$_SERVER['HTTP_HOST']}/";
		}
		else {
			return "https://{$_ENV['app_subdomain']}.{$_ENV['domain']}/";
		}
	}

	/**
	 * Formats strings and ensures that they won't break SQL.
	 * If a string is not a float, int or string,
	 * it will be outright rejected.
	 * <code>
	 * str::i($string);
	 * </code>
	 * or if HTML is accepted
	 * <code>
	 * str::i($html_string, TRUE);
	 * </code>
	 *
	 * @param float|int|string $i
	 * @param bool             $html_accepted
	 *
	 * @return mixed|string
	 */
	public static function i($i, $html_accepted = NULL)
	{
		# We don't really care about stuff that looks like numbers
		if(is_numeric($i)){
			return $i;
		}

		if(!is_string($i)){
			return false;
		}

		if(!$html_accepted){
			//if HTML is *NOT* accepted
			$i = @strip_tags($i);
			/**
			 * htmlspecialchars did NOT work. Need to investigate why.
			 * Replaces & " ' < and > with their HTML counterparts, that's it.
			 */
		}

		$i = self::mysql_escape_mimic($i);
		/**
		 * This prevents mySQL injections by prefixing most
		 * metacharacters (quotation marks, etc.) with an escape character.
		 */

		$i = trim($i);
		/**
		 * The string is trimmed at both ends by design.
		 * This may have unintended consequences.
		 */

		return $i;
	}

	/**
	 * DEPRECATED
	 *
	 * Has been prone to failing valid email addresses.
	 * Replaced with filter_var($email, FILTER_VALIDATE_EMAIL)
	 *
	 * Checks to see if an email address is valid
	 *    <code>str::isValidEmail($email);<code>
	 *
	 * @param string $email email@address.com
	 *
	 * @return boolean returns the email address on true or false on failure
	 * @link: https://github.com/egulias/EmailValidator
	 */
	public static function isValidEmail($email)
	{
		$validator = new \Egulias\EmailValidator\EmailValidator();
		$multipleValidations = new \Egulias\EmailValidator\Validation\MultipleValidationWithAnd([
			new \Egulias\EmailValidator\Validation\RFCValidation(),
			new \Egulias\EmailValidator\Validation\DNSCheckValidation(),
		]);
		return $validator->isValid($email, $multipleValidations);
	}

	/**
	 * Using the ">" character as the delimiter, this regex will match
	 * any valid email address, including emails inside Outlook style strings.
	 *
	 * @link https://stackoverflow.com/a/201378/429071
	 * @link https://emailregex.com/
	 */
	const EMAIL_REGEX = <<<EOF
>(?:[a-z0-9!#$%&'*+/=?^_`{|}~-]+(?:\.[a-z0-9!#$%&'*+/=?^_`{|}~-]+)*|"(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21\x23-\x5b\x5d-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])*")@(?:(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?|\[(?:(?:(2(5[0-5]|[0-4][0-9])|1[0-9][0-9]|[1-9]?[0-9]))\.){3}(?:(2(5[0-5]|[0-4][0-9])|1[0-9][0-9]|[1-9]?[0-9])|[a-z0-9-]*[a-z0-9]:(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21-\x5a\x53-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])+)\])>ui
EOF;


	/**
	 * Given a string, potentially containing multiple email addresses,
	 * will return an array of valid email addresses.
	 *
	 * @param string|null $string
	 *
	 * @return array|null
	 */
	public static function getEmailArrayFromString(?string $string): ?array
	{
		if(!$string){
			return NULL;
		}

		// Array to hold the matches
		$matches = [];

		// Execute the regex
		preg_match_all(self::EMAIL_REGEX, $string, $matches);

		return array_unique($matches[0]);
	}

	/**
	 * Checks to see whether an email is NOT a known spam email (or not, meaning it is).
	 * Should be used after the `isValidEmail()` method for double safety.
	 *
	 * @param string $email
	 *
	 * @return bool
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 * @link https://www.stopforumspam.com/usage
	 */
	public static function isNotSpamEmail(string $email): bool
	{
		$client = new Client([
			"base_uri" => "http://api.stopforumspam.org/",
		]);

		$request = $client->request("POST", "api", [
			"headers" => [
				'Content-Type' => 'application/x-www-form-urlencoded',
			],
			"query" => [
				"email" => $email,
				"json" => true,
			],
		]);

		# Get the body (as an array)
		$json = $request->getBody()->getContents();
		$array = json_decode($json, true);

		/**
		 * array(2) {
		 * ["success"]=>
		 * int(1)
		 * ["email"]=>
		 * array(5) {
		 * ["value"]=>
		 * string(23) "aleksandra.626@mail.com"
		 * ["appears"]=>
		 * int(1)
		 * ["frequency"]=>
		 * int(33)
		 * ["lastseen"]=>
		 * string(19) "2021-03-14 11:31:36"
		 * ["confidence"]=>
		 * string(5) "88.00"
		 * }
		 * }
		 */

		return !$array['email']['appears'];
	}

	/**
	 * Checks to see if a given string is JSON or not.
	 *
	 * @param mixed|null $str
	 *
	 * @return bool
	 */
	public static function isJson($str): bool
	{
		# Must be a string
		if(!is_string($str)){
			return false;
		}

		# Must start with either "{" or "["
		if(!in_array(substr(trim($str), 0, 1), ["{", "["])){
			return false;
		}

		# Must end with either "}" or "]"
		if(!in_array(substr(trim($str), -1), ["}", "]"])){
			return false;
		}

		# Must decode without error
		json_decode($str);
		return json_last_error() === JSON_ERROR_NONE;
	}

	/**
	 * Checks to see if a given string is base64 or not.
	 *
	 * @param string|null $str
	 *
	 * @return bool
	 */
	public static function isBase64(?string $str): bool
	{
		if(!$str){
			return false;
		}

		return base64_encode(base64_decode($str, true)) === $str;
	}

	/**
	 * Validates a phone number.
	 *
	 * A valid phone number will consist of the following characters only:
	 * - numbers 0123456789
	 * - the plus symbol +27123456789
	 * - periods +27.123.456.789
	 * - dashes +27-123-456-789
	 * - parenthesis +27(0)123-456-789
	 * - spaces +27 (0)123-456-789
	 * - the pound symbol +27(0)123-456-789#1234
	 *
	 * @param $number
	 *
	 * @return bool
	 */
	public static function isValidPhoneNumber($number)
	{
		# Ensure it only contains valid characters
		if(preg_match("/[^0-9\.\-\(\)\s#\+']/", $number)){
			return false;
		}

		# Ensure it fits the minimum length of a phone number
		if(strlen(preg_replace("/[^0-9]+/", "", $number)) < self::MINIMUM_PHONE_NUMBER_LENGTH){
			return false;
		}

		return true;
	}

	/**
	 * Will return true if the datetime object modify string is valid.
	 * False if it's not. Will not raise a warning.
	 *
	 * @param string|null $modify
	 *
	 * @return bool
	 */
	public static function isValidModify(?string $modify): bool
	{
		$dt = new \DateTime();
		return (bool)@$dt->modify($modify);
	}

	/**
	 * @link http://php.net/manual/en/function.mysql-real-escape-string.php#101248
	 *
	 * @param array|float|int|string $inp
	 *
	 * @return array|float|int|string
	 */
	public static function mysql_escape_mimic($inp)
	{
		if(is_array($inp))
			return array_map(__METHOD__, $inp);

		if(!empty($inp) && is_string($inp)){
			return str_replace(['\\', "'", '"', "\x1a"], ['\\\\', "\\'", '\\"', '\\Z'], $inp);
		}

		return $inp;
	}

	/**
	 * Given a classic $a array, replaces
	 * all the "NULL" string vars with NULL values.
	 * Use with care to avoid hacking SQL queries.
	 *
	 * @param $a
	 */
	public static function replaceNullStrings(&$a): void
	{
		if(is_array($a['vars'])){
			foreach($a['vars'] as $key => $val){
				if($val == "NULL"){
					$a['vars'][$key] = NULL;
				}
			}
		}
	}

	/**
	 * Checks to see if a given method is available in the current scope.
	 * And if that method is PUBLIC. Protected and private methods
	 * are protected from outside execution via the load_ajax_call() call.
	 * If the modifier is set, it will accept any methods set at that modifier or lower:
	 * <code>
	 * private
	 * protected
	 * public
	 * </code>
	 *
	 * @param object $class
	 * @param string $method
	 * @param string $modifier The minimum accepted modifier level. (Default: public)
	 *
	 * @return bool
	 * @link https://stackoverflow.com/questions/4160901/how-to-check-if-a-function-is-public-or-protected-in-php
	 */
	public static function methodAvailable(object $class, string $method, $modifier = "public"): bool
	{
		if(!$class || !$method){
			return false;
		}

		if(!method_exists($class, $method)){
			return false;
		}

		try {
			$reflection = new \ReflectionMethod($class, $method);
		}
		catch(\ReflectionException $e) {
			return false;
		}

		switch($modifier) {
		case 'private':
			if(!$reflection->isPrivate() && !$reflection->isProtected() && !$reflection->isPublic()){
				return false;
			}
			break;
		case 'protected':
			if(!$reflection->isProtected() && !$reflection->isPublic()){
				return false;
			}
			break;
		case 'public':
		default:
			if(!$reflection->isPublic()){
				return false;
			}
			break;
		}

		return true;
	}

	/**
	 * Given a folder path, returns an array
	 * with all the classes found.
	 *
	 * @param string $path
	 *
	 * @return array
	 */
	public static function getClassesFromPath(string $path, ?string $implementation = NULL): array
	{
		$finder = new \Symfony\Component\Finder\Finder;
		$iter = new \hanneskod\classtools\Iterator\ClassIterator($finder->in($path));

		if(!$classes = array_keys($iter->getClassMap())){
			return [];
		}

		if($implementation){
			$classes_with_implementation = [];

			foreach($classes as $class){
				if(!class_exists($class)){
					continue;
				}
				if(!$implementations = class_implements($class)){
					continue;
				}
				if(!$implementations[$implementation]){
					continue;
				}
				$classes_with_implementation[] = $class;
			}

			return $classes_with_implementation;
		}

		return $classes;
	}

	/**
	 * Returns an MD5 of an array or string.
	 * Will convert binary strings to base64.
	 *
	 * Case-insensitive.
	 *
	 * @param array|string $value
	 *
	 * @return string
	 */
	public static function getHash($value): ?string
	{
		# If the value is NULL, get the hash of an empty string
		if($value === NULL){
			return md5("");
		}

		# Order keys alphabetically (to ensure that the JSON string is always the same)
		if(is_array($value)){
			ksort($value);
		}

		return md5(mb_strtolower(str::json_encode($value, "base64")));
	}

	/**
	 * Given a class name (with its namespace),
	 * return an array of all the methods that
	 * qualify the modifier.
	 * This depends on the root namespaces being in the composer.json
	 * and that the `composer update autoload` command has been run.
	 *
	 * @param string      $class
	 * @param string|null $modifier Default modifier is PUBLIC
	 *
	 * @return array
	 */
	public static function getMethodsFromClass(string $class, ?string $modifier = "PUBLIC"): array
	{
		$modifier = strtoupper($modifier);
		$cmd  = "\\Swoole\\Coroutine\\run(function(){";
		$cmd .= "require \"/var/www/html/app/settings.php\";";
		$cmd .= "\$class = new \ReflectionClass(\"" . str_replace("\\", "\\\\", $class) . "\");";
		$cmd .= "echo json_encode(\$class->getMethods(\ReflectionMethod::IS_{$modifier}));";
		$cmd .= "});";

		# Run the command
		exec("php -r '{$cmd}' 2>&1", $output);

		# Temporary filter before Swoole 4.6+
		$output = array_filter($output, function($line){
			return $line != "Deprecated: Swoole\Event::rshutdown(): Event::wait() in shutdown function is deprecated in Unknown on line 0";
		});

		# Return false if no methods matching are found
		if(!$array = json_decode($output[0], true)){
			return [];
		}

		foreach($array as $method){
			# We're not interested in inherited classes
			if($class != $method['class']){
				continue;
			}

			# We're not interested in magic methods
			if(substr($method['name'], 0, 2) == "__"){
				continue;
			}

			# Collect all the methods
			$methods[] = $method;
		}

		return $methods;
	}

	/**
	 * Converts snake_case to methodCase (camelCase).
	 *
	 * @param $snake
	 *
	 * @return string
	 * @return string
	 */
	public static function getMethodCase(?string $snake): ?string
	{
		if($snake == NULL){
			return NULL;
		}

		return lcfirst(str_replace("_", "", ucwords($snake, "_")));
	}

	/**
	 * Given a rel_table, and an optional parent and grandparent class, find a class
	 *
	 * @param string|null $rel_table         The class you're looking for
	 * @param string|null $parent_class      The parent class if it's different from the class itself
	 * @param string|null $grandparent_class The optional grandparent class, if the info class is a level deeper. Only
	 *                                       applies to API info classes
	 *
	 * @return null|string Returns the class with path or NULL if it can't find it
	 */
	public static function findClass(?string $rel_table, ?string $parent_class = NULL, ?string $grandparent_class = NULL): ?string
	{
		# In case a class name is NOT sent for some reason.
		if(!$rel_table){
			return NULL;
		}

		# An optional grandparent class can be supplied, and the parent class will be the same as the rel_table if not provided
		$suffix = implode("\\", array_filter([$grandparent_class ?: NULL, $parent_class ?: $rel_table, $rel_table]));

		$prefixes = [
			# App path
			"\\App\\",

			# Common path
			"\\App\\Common\\",

			# API path
			"\\API\\",
		];

		foreach($prefixes as $prefix){
			$path = str::getClassCase($prefix . $suffix);
			$paths[] = $path;

			if(class_exists($path)){
				return $path;
			}
		}

		# If no class can be found
		return NULL;
	}

	/**
	 * Converts snake_case to ClassCase (CamelCase).
	 * Will also convert \App\common\rel_table to \App\Common\RelTable
	 *
	 * @param string|null $snake
	 *
	 * @return string|null
	 */
	public static function getClassCase(?string $snake): ?string
	{
		# If nothing is passed, return nothing
		if($snake === NULL){
			return NULL;
		}

		# Capitalize the first letter of each word
		$snake = ucwords($snake, "_\\");

		# Ensure there is at least one underscore or backslash
		if(strpos($snake, "_") === false && strpos($snake, "\\") === false){
			// Otherwise, there is no need to convert anything
			return $snake;
		}

		return str_replace("_", "", $snake);
	}

	/**
	 * Given a camelCase string returns snake_case.
	 * Will also replace spaces with underscore.
	 *
	 * @param string $string
	 * @param string $us
	 *
	 * @return string
	 * @link https://stackoverflow.com/a/40514305/429071
	 */
	public static function camelToSnakeCase(?string $string, string $us = "_"): string
	{
		return str_replace(" ", $us, mb_strtolower(preg_replace(
			'/(?<=\d)(?=[A-Za-z])|(?<=[A-Za-z])(?=\d)|(?<=[a-z])(?=[A-Z])/u', $us, $string)));
	}

	/**
	 * Given a camelCase string returns kebab-case.
	 * Somewhat redundant, as the camelToSnakeCase can
	 * also perform this transformation, but it's here
	 * for completeness.
	 *
	 * @param string $string
	 *
	 * @return string
	 */
	public static function camelToKebabCase(string $string): string
	{
		return mb_strtolower(preg_replace('/(?<!^)[\p{L}]/u', '-$0', $string));
	}

	/**
	 * Given two or more strings
	 * (or a whole multidimensional array of data),
	 * create a checksum that can be used to
	 * validate the completeness of the data.
	 *
	 * @param array $a Array order doesn't matter, as the array will be sorted before the CRC32 checksum is created
	 *
	 * @return int the CRC32 checksum
	 */
	public static function generateChecksum(array $a): int
	{
		array_multisort($a);
		return crc32(implode("", str::flatten($a)));
	}

	/**
	 * Given an array of data and its checksum,
	 * compare it and return the results.
	 *
	 * @param array  $a        Array of data
	 * @param string $checksum Checksum belonging to the data
	 *
	 * @return bool Returns TRUE if the data is complete, FALSE if not.
	 */
	public static function validateChecksum(array $a, string $checksum): bool
	{
		return ($checksum == str::generateChecksum($a));
	}


	/**
	 * Given an array of rel_table/id and action, and an array of vars,
	 * creates a hash string and returns it.
	 * Crucially, the string is not prefixed with a slash.
	 *
	 * @param array|string|null $array_or_string
	 * @param bool|null         $urlencoded If set to yes, will urlencode the hash string
	 *
	 * @return string
	 */
	static function generate_uri($array_or_string, ?bool $urlencoded = NULL, ?bool $for_email = NULL): ?string
	{

		if(!$array_or_string){
			return NULL;
		}

		# If an array is given (most common)
		if(is_array($array_or_string)){
			extract($array_or_string);
			# Ensure there always is some sort of rel_id

			if($for_email){
				$rel_id = $rel_id ?: "-";
				$action = $action ?: "-";
				// Avoids the issue of Microsoft replacing // with / in emails
			}

			$hash = "{$rel_table}/{$rel_id}/{$action}";

			if(!$vars){
				//if there are no variables attached

				# Remove any surplus slashes and dashes at the end
				$hash = rtrim($hash, "/-");
			}
		}

		# If a string is given (less common)
		if(is_string($array_or_string)){
			$hash = $array_or_string;
		}

		# If there are vars (in the array)
		if(is_array($vars)){
			# Callbacks can be their own URI in array form, make sure they're converted to string
			if(is_array($vars['callback'])){
				$vars['callback'] = str::generate_uri($vars['callback'], true);
			}
			if(count($vars) == count($vars, COUNT_RECURSIVE)){
				//if the vars array is NOT multidimensional
				foreach($vars as $key => $val){
					if($val === NULL){
						//Only if they're literally NULL, shall they be ignored
						continue;
					}
					$hash .= "/$key/$val";
				}
			}
			else {
				//if the vars array _is_ multidimensional
				$hash .= "/" . json_encode($vars);
				//Encode the entire vars array as a JSON string
				//NULL values WILL be encoded also
			}
			/**
			 * The reason why multi-dimensional variable arrays are
			 * treated differently is because otherwise they wouldn't
			 * fit into the key/val/.../key/val structure of the
			 * URI string, and any second and n-level key values would
			 * be lost.
			 *
			 * Instead, the entire multidimensional array is stored
			 * as a string and converted back to an array by the
			 * hashChange() method in Javascript.
			 *
			 * NULL values WILL be encoded. This is different from the key/val system.
			 */
		}

		# Allow for vars to be sent as base64 encoded strings
		else if(is_array(str::base64_decode_url($vars))){
			$hash .= "/" . $vars;
		}

		# Return, URL encoding optional
		return $urlencoded ? self::urlencode($hash) : $hash;
	}

	/**
	 * Safe URL encoding.
	 * Only URL encodes if the string needs it, or hasn't already been encoded.
	 *
	 * @param string|null $str
	 *
	 * @return string
	 */
	static function urlencode(?string $str): ?string
	{
		if($str === NULL){
			return NULL;
		}
		return strpos($str, '/') !== false ? urlencode($str) : $str;
	}

	/**
	 * URL decodes a variable. That variable
	 * could be an array with key-val pairs.
	 * All the vals will be urldecoded.
	 *
	 * @param $a
	 *
	 * @return array|string
	 */
	static function urldecode($a)
	{
		if(!$a){
			return $a;
		}
		if(is_array($a)){
			foreach($a as $key => $val){

				if($val === NULL){
					continue;
				}

				if(is_array($val)){
					$a[$key] = $val;
					continue;
				}

				$a[$key] = urldecode($val);
			}
			return $a;
		}
		return urldecode($a);
	}

	/**
	 * Given a url + optional key/vals, generate a URL.
	 * Not to confused with a hash URI.
	 *
	 * @param $url
	 * @param $hash_array
	 *
	 * @return string
	 */
	static function generate_url($url, $hash_array = NULL)
	{
		if(is_array($hash_array)){
			$url .= "?";
			foreach($hash_array as $key => $val){
				$keys[] = "{$key}={$val}";
			}
			$url .= implode("&", $keys);
		}

		return $url;
	}

	/**
	 * Same as PHP's explode, except delimiter can be an array of strings
	 *
	 * @param array|string $delimiters
	 * @param array|string $string
	 *
	 * @return array|null
	 */
	static function explode($delimiters, $string): ?array
	{
		if(!is_array(($delimiters)) && !is_array($string)){
			//if neither the delimiter nor the string are arrays
			return explode($delimiters, $string);
		}

		else if(!is_array($delimiters) && is_array($string)){
			//if the delimiter is not an array but the string is
			$items = [];
			foreach($string as $item){
				$items = array_merge($items, explode($delimiters, $item));
			}
			return $items;
		}

		else if(is_array($delimiters) && !is_array($string)){
			//if the delimiter is an array but the string is not
			$string_array[] = $string;
			foreach($delimiters as $delimiter){
				$string_array = self::explode($delimiter, $string_array);
			}
			return $string_array;
		}

		else if(is_array($delimiters) && is_array($string)){
			//if both the delimiter and the string are arrays
			$items = [];
			foreach($string as $item){
				foreach($delimiters as $delimiter){
					$items = array_merge($items, explode($delimiter, $item));
				}
			}
			return $items;
		}

		return NULL;
	}

	/**
	 * Searches an array of needles in a string haystack
	 *
	 * @param string $haystack
	 * @param array  $needle
	 * @param int    $offset
	 *
	 * @return bool
	 * @link http://stackoverflow.com/questions/6284553/using-an-array-as-needles-in-strpos
	 */
	public static function strposa($haystack, $needle, $offset = 0)
	{
		if(!is_array($needle))
			$needle = [$needle];
		foreach($needle as $query){
			if(strpos($haystack, $query, $offset) !== false)
				return true; // stop on first true result
		}
		return false;
	}

	/**
	 * Replace something _once_.
	 *
	 * @param string $needle
	 * @param string $replace
	 * @param string $haystack
	 * @param bool   $last if set to TRUE, will replace the *LAST* instance,
	 *                     instead of the default first
	 *
	 * @return mixed
	 * @link https://stackoverflow.com/a/1252710/429071
	 */
	static function str_replace_once($needle, $replace, $haystack, $last = NULL)
	{
		if($last){
			$pos = strrpos($haystack, $needle);
		}
		else {
			$pos = strpos($haystack, $needle);
		}

		if($pos !== false){
			return substr_replace($haystack, $replace, $pos, strlen($needle));
		}

		return $haystack;
	}

	/**
	 * Case-insensitive in_array function.
	 *
	 * @param $needle
	 * @param $haystack
	 *
	 * @return bool
	 * @link https://www.php.net/manual/en/function.in-array.php#89256
	 */
	public static function in_array_ci($needle, $haystack)
	{
		return in_array(strtolower($needle), array_map('strtolower', $haystack));
	}

	/**
	 * Given a snippet of text, will replace
	 * line breaks with HTML line breaks <br>.
	 *
	 * @param string|null $string
	 *
	 * @return string
	 */
	static function newline(?string $string): string
	{
		return str_replace(["\r\n", "\r", "\n"], "<br>", $string);
	}

	/**
	 * Given a number, assumed to be bytes,
	 * returns the corresponding value in B-TB,
	 * with suffix, or not.
	 *
	 * @param int       $bytes          A number of bytes
	 * @param int|null  $precision      How precise to represent the number
	 * @param bool|null $include_suffix Whether or not to include the suffix (default is yes)
	 *
	 * @return string
	 */
	static function bytes($bytes, ?int $precision = 2, ?bool $include_suffix = true)
	{
		$units = ['B', 'KB', 'MB', 'GB', 'TB'];

		$bytes = max($bytes, 0);
		$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
		$pow = min($pow, count($units) - 1);
		$bytes /= pow(1024, $pow);

		if(!$include_suffix){
			return round($bytes, $precision);
		}

		return round($bytes, $precision) . ' ' . $units[$pow];
	}

	/**
	 * Takes a javascript snippet and wraps it in a `<script>` tag.
	 * Checks to make sure that the snippet is not empty,
	 * and that the snipped isn't already wrapped with the tag.
	 *
	 * @param $script
	 *
	 * @return bool|string
	 */
	static function getScriptTag($script)
	{
		if(!$script){
			return false;
		}

		if(substr(trim(mb_strtolower($script)), 0, strlen("<script>")) == "<script>"){
			return $script;
		}

		return "<script>{$script}</script>";
	}

	/**
	 * Returns an int as the depth of the array.
	 *
	 * @param $array
	 *
	 * @return int
	 */
	static function get_array_depth($array)
	{
		$depth = 0;
		$iteIte = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($array));

		foreach($iteIte as $ite){
			$d = $iteIte->getDepth();
			$depth = $d > $depth ? $d : $depth;
		}

		return $depth;
	}

	/**
	 * Returns an array of (attribute) values.
	 *
	 * @param array|string|bool $attr      An array or string of values to be added to the attribute. If set to false,
	 *                                     will override $defaults.
	 * @param array|string      $defaults  The default values of the attribute.
	 * @param array|string      $only_attr If set, will override both $attr and $defaults, this includes if it's set to
	 *                                     false.
	 *
	 * @return array|null
	 */
	static function getAttrArray($attr = NULL, $defaults = NULL, $only_attr = NULL)
	{
		if($only_attr === false){
			return [];
		}

		if($only_attr){
			return is_array($only_attr) ? $only_attr : [$only_attr];
		}

		return array_merge(
			is_array($defaults) ? $defaults : [$defaults],
			is_array($attr) ? $attr : [$attr]
		);
	}

	/**
	 * Given an attribute and a value, returns a string that can be fed into a tag.
	 * If $attr = "key" and $val = "value", will return:
	 * <code>
	 * key="value"
	 * </code>
	 *
	 * @param       $attr
	 * @param mixed $val     Can be a string, can be an array
	 * @param null  $if_null Replacement value if the $val variable is empty (string length = 0)
	 *
	 * @return bool|string
	 */
	static function getAttrTag($attr, $val, $if_null = NULL)
	{
		if(is_array($val)){
			$val_array = $val;
			unset($val);
			if(str::isNumericArray($val_array)){
				//if keys don't matter
				array_walk_recursive($val_array, function($a) use (&$flat_val_array){
					$flat_val_array[] = $a;
				});
				//flatten the potentially multidimensional array
				$val = implode(" ", array_unique(array_filter($flat_val_array)));
				//remove empty and duplicate values, and then translate the array to a string
			}
			else {
				//if keys do matter
				foreach($val_array as $k => $v){
					if(is_numeric($k) && is_array($v)){
						foreach($v as $vk => $vv){
							$val .= "{$vk}:{$vv};";
						}
					}
					if(is_numeric($k) && !is_array($v)){
						$val .= $v;
					}
					else if(!is_array($k) && is_array($v)){
						$val .= "{$k}:" . end($v) . ";";
					}
					else {
						if($k && strlen($v)){
							$val .= "{$k}:{$v};";
						}
					}
				}
			}
		}

		if($val === NULL || !strlen(trim($val))){
			// If val is NULL or empty, return the replacement value

			if($if_null === true){
				//If you just need there to be a tag, even if the value is empty
				$val = "";
			}

			else if($if_null){
				//if there is a replacement
				$val = $if_null;
			}

			else {
				//otherwise peace out
				return NULL;
			}
		}

		$val = str_replace("\"", "&quot;", $val ?? "");
		//make sure the val doesn't break the whole tag
		//@link https://stackoverflow.com/a/1081581/429071

		if(!$attr){
			//if there is no attribute, just return the value (now a string)
			return " {$val}";
		}

		return " {$attr}=\"{$val}\"";
	}

	/**
	 * Given a text string, will return it as light gray and italicised text.
	 *
	 * @param string|null $text
	 *
	 * @return string
	 */
	public static function muteText(?string $text): string
	{
		return "<i class=\"text-silent\">{$text}</i>";
	}

	/**
	 * Creates a random ID to give to divs.
	 * The format is `prefix_n` where N is a number generated by the `rand()` function.
	 * The prefix is optional, and will be formatted to fit jQuery + PHP formatting restrictions,
	 * meaning only alphanumeric characters are allowed.
	 *
	 * @param string $prefix
	 *
	 * @return string
	 */
	public static function id($prefix = NULL)
	{
		$prefix = $prefix ?: "id";
		$prefix = preg_replace("/[^A-Za-z0-9]/", '_', $prefix);
		return "{$prefix}_" . rand();
	}

	/**
	 * Generates a UUID version 4, like so:
	 * <code>
	 * 1ee9aa1b-6510-4105-92b9-7171bb2f3089
	 * </code>
	 * Add a `$length` value, and only a certain section is returned.
	 * The ID is completely random and not generated in any sequential order.
	 *
	 * @param int $length 4, 8 or 12 are the only accepted values
	 *
	 * @return mixed|string
	 */
	public static function uuid($length = NULL)
	{
		$obj = \Ramsey\Uuid\Uuid::uuid4();
		$uuid = $obj->toString();

		switch($length) {
		case 4: # Return the second section (4 characteres) of the UUID
			return explode("-", $uuid)[1];
			break;
		case 8: # Return the second and penultimate sections (in total 8 characters)
			return explode("-", $uuid)[1] . explode("-", $uuid)[3];
			break;
		case 12: # Return the last section (12 characters) of the UUID
			return explode("-", $uuid)[4];
			break;
		default: # Return the entire 36 character string
			return $uuid;
			break;
		}
	}

	/**
	 * Checks to see if a given string is a UUID (matches a UUID pattern).
	 *
	 * @param string|null $string String is case-insensitive.
	 *
	 * @return bool Returns TRUE if the pattern matches given subject,
	 *                FALSE if it does not or if an error occurred.
	 */
	public static function isUuid(?string $string): bool
	{
		return preg_match("/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i", $string);
	}

	/**
	 * Returns a green check if $value is TRUE.
	 * Returns a red cross if $cross is TRUE and $value is FALSE.
	 * Settings allow for further style and class overrides.
	 *
	 * @param            $value
	 * @param bool       $cross
	 * @param array|null $settings Additional settings fed to the icon generator
	 *
	 * @return string|bool
	 */
	static function check($value, $cross = false, ?array $settings = [])
	{
		if($value){
			$settings['name'] = $settings['name'] ?: "check";
			$settings['colour'] = $settings['colour'] ?: "success";
			return Icon::generate($settings);
		}
		else if($cross){
			$settings['name'] = $settings['name'] ?: "times";
			$settings['colour'] = $settings['colour'] ?: "danger";
			return Icon::generate($settings);
		}
		return false;
	}

	/**
	 * Return an error message if the given pattern argument or its underlying regular expression
	 * are not syntactically valid. Otherwise, (if they are valid), NULL is returned.
	 *
	 * @param $pattern
	 *
	 * @return string|null
	 */
	public static function regexHasErrors(string $pattern): ?string
	{
		if(@preg_match($pattern, '') === false){
			return str_replace("preg_match(): ", "", error_get_last()["message"]);
			//Make it prettier by removing the function name prefix
		}
		return NULL;
	}

	/**
	 * Given an mySQL datetime value, will return a HTML string with JavaScript
	 * that will display the amount of time *ago* that timestamp was.
	 * The amount of time will change as time passes.
	 *
	 * @param \DateTime|string|null $dt     mySQL datetime value
	 * @param bool|null             $future If set to true will also show future times
	 *
	 * @return string
	 * @throws \Exception
	 */
	static function ago($dt, ?bool $future = NULL, ?bool $ignore_suffix = NULL): ?string
	{
		# Empty
		if(!$dt){
			return NULL;
		}

		# Ensure we have a DateTime object
		if(!is_object($dt)){
			$dt = new \DateTime($dt);
		}

		# The element ID (for uniqueness)
		$id = str::getAttrTag("id", str::id("timeago"));

		# The class (for triggering the JavaScript)
		$class = str::getAttrTag("class", ["timeago", $future ? "allow-future" : NULL]);

		# The datetime attribute, as required by the timeAgo jQuery plugin
		$datetime = str::getAttrTag("datetime", $dt->format('c'));

		if(!$ignore_suffix){
			$suffix = " [{$dt->format('j M Y')}]";
		}

		return "<time{$id}{$class}{$datetime}>{$dt->format("Y-m-d H:i:s")}</time>{$suffix}";
	}

	/**
	 * Input:
	 * <code>
	 * echo time_elapsed_string('@1367367755'); # timestamp input
	 * echo time_elapsed_string('2013-05-01 00:22:35', true);
	 * </code>
	 * Output:
	 * <code>
	 * 4 months ago
	 * 4 months, 2 weeks, 3 days, 1 hour, 49 minutes, 15 seconds ago
	 * </code>
	 * The output is static.
	 *
	 * @param      $datetime
	 * @param bool $full
	 *
	 * @return string
	 * @throws \Exception
	 * @throws \Exception
	 * @link https://stackoverflow.com/a/18602474/429071
	 */
	static function time_elapsed_string($datetime, $full = false)
	{
		$now = new \DateTime;
		$ago = new \DateTime($datetime);
		$diff = $now->diff($ago);

		$diff->w = floor($diff->d / 7);
		$diff->d -= $diff->w * 7;

		$string = [
			'y' => 'year',
			'm' => 'month',
			'w' => 'week',
			'd' => 'day',
			'h' => 'hour',
			'i' => 'minute',
			's' => 'second',
		];
		foreach($string as $k => &$v){
			if($diff->$k){
				$v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
			}
			else {
				unset($string[$k]);
			}
		}

		if(!$full)
			$string = array_slice($string, 0, 1);
		return $string ? implode(', ', $string) . ' ago' : 'just now';
	}

	/**
	 * Returns a string of how many days between then and now.
	 *
	 * @param      $date   mySQL date formatted string (YYYY-MM-DD)
	 * @param bool $string if TRUE, the function may return a string variable (n days, yesterday, tomorrow, etc), if
	 *                     FALSE, will always just return a number
	 *
	 * @return bool|string
	 */
	static function days($date, $string = false)
	{
		if(!$date){
			return false;
		}
		$then = \DateTime::createFromFormat("Y-m-d", $date);
		$now = new \DateTime();
		$days_away = (int)$then->diff($now)->format("%r%a");
		if(!$string){
			return $days_away;
		}
		switch($days_away) {
		case -1:
			return "yesterday";
			break;
		case 1:
			return "tomorrow";
			break;
		default:
			return "{$days_away} days";
			break;
		}
	}

	/**
	 * Given an Y-m-d date, will return a formatted date
	 * and a badge of how many years ago that was.
	 *
	 * @param string|null $ymd
	 *
	 * @return string|null
	 * @throws \Exception
	 */
	static function birthday(?string $ymd): ?string
	{
		if(!$ymd){
			return NULL;
		}

		$years_old = floor((time() - strtotime($ymd)) / 31556926);

		$html = (new \DateTime($ymd))->format("d M Y");
		$html .= Badge::generate([
			"style" => [
				"margin-left" => "0.5rem",
			],
			"basic" => true,
			"title" => $years_old,
			"alt" => "{$years_old} years old this year",
		]);

		return $html;
	}

	/**
	 * Checks if the number of minutes between a given
	 * point in time in the `$since` variable expressed
	 * as any value that the `strtotime()` function can
	 * interpret, and now, and whether it's more than
	 * `$minutes`.
	 * <code>
	 * if(str::moreThanMinutesSince(15, $user['updated'])){
	 *    //If more than 15 minutes has passed since the user profile was last updated
	 * }
	 * </code>
	 *
	 * @param float  $minutes
	 * @param string $since
	 *
	 * @return bool
	 */
	static function moreThanMinutesSince(float $minutes, string $since): bool
	{
		return (floor((strtotime("now") - strtotime($since)) / 60) >= $minutes);
	}

	/**
	 * Transforms int and float into readable numbers
	 * or currency, with optional padding.
	 *
	 * @param float|null  $amount
	 * @param string|null $currency
	 * @param int|null    $padding
	 * @param int|null    $decimals
	 * @param string|null $thousands_separator
	 *
	 * @return string|null
	 */
	static function number(?float $amount, ?string $currency = NULL, ?int $padding = NULL, ?int $decimals = 2, ?string $thousands_separator = ",", ?bool $monospace = true): ?string
	{
		# A number (even if it's "0") is required
		if(!strlen($amount)){
			return NULL;
		}

		# include thousand-separators, decimals and a decimal point
		$amount = number_format($amount, $decimals, '.', $thousands_separator);

		# Pad if required
		if($padding){
			$amount = str_pad($amount, $padding, " ", STR_PAD_LEFT);
		}

		# Prefix with currency symbol + space
		if($currency){
			$amount = "{$currency} {$amount}";
		}

		if($monospace){
			$amount = str::monospace($amount);
		}

		return $amount;
	}

	/**
	 * Format any text as monospaced.
	 *
	 * @param mixed $a
	 *
	 * @return string
	 */
	static function monospace($a): ?string
	{
		if(!is_array($a)){
			# There needs to be _something_ to monospace
			if(!strlen($a)){
				return NULL;
			}

			$a = [
				"text" => $a,
			];
		}

		extract($a);

		# Make uppercase
		if($upper){
			$text = strtoupper($text);
		}

		# Replace spaces with &nbsp; (seems to be the only way to enforce spacing)
		$text = str_replace(" ", "&nbsp;", $text ?: $html);

		# ID (optional)
		$id = str::getAttrTag("id", $id);

		# Class
		$class_array = str::getAttrArray($class, ["text-monospace"], $only_class);
		$class = str::getAttrTag("class", $class_array);

		# Style
		$style = str::getAttrTag("style", $style);

		# Alt
		$alt = str::getAttrTag("title", $alt);

		# Tag
		$tag = $tag ?: "span";

		return "<{$tag}{$id}{$class}{$style}{$alt}>{$text}</{$tag}>";
	}

	/**
	 * Get today's date (only).
	 * Time has been stripped away.
	 *
	 * @return \DateTime
	 */
	public static function today(): \DateTime
	{
		# Get today's date (only)
		$today = new \DateTime();
		$today->setTime(0, 0, 0, 0);
		return $today;
	}

	/**
	 * Returns UTC time wrapped in a span that is handled by JavaScript,
	 * and converted to local time.
	 *
	 * @param string|null $datetime_string
	 * @param bool|null   $time
	 * @param bool|null   $date
	 *
	 * @return string
	 */
	public static function jsDateTime(?string $datetime_string, ?bool $time = true, ?bool $date = true, ?bool $timezone = false): string
	{
		$date = $date ? "date" : NULL;
		$time = $time ? "time" : NULL;
		$timezone = $timezone ? "timezone" : NULL;
		return "<span class=\"js-datetime {$date} {$time} {$timezone}\">{$datetime_string}</span>";
	}

	/**
	 * Given a date string, returns a datetime object,
	 * where the time is set to 00:00:00.
	 *
	 * Will return today's datetime if no value is passed,
	 * or if the value is invalid.
	 *
	 * @param string|\DateTime $date
	 *
	 * @return \DateTime
	 * @throws \Exception
	 */
	public static function newDateTimeDateOnly($date = NULL): \DateTime
	{
		if($date instanceof \DateTime){
			$dt = $date;
		}

		else {
			try {
				$dt = new \DateTime($date);
			}
			catch(\Exception $e) {
				$dt = new \DateTime();
			}
		}

		$dt->setTime(0, 0, 0);

		return $dt;
	}


	/**
	 * var_export() with square brackets and indented 4 spaces.
	 *
	 * @param      $expression
	 * @param bool $return
	 *
	 * @return string
	 * @link https://www.php.net/manual/en/function.var-export.php#122853
	 */
	public static function var_export($expression, $return = false)
	{
		$export = var_export($expression, true);
		$export = preg_replace("/^([ ]*)(.*)/m", '$1$1$2', $export);
		$array = preg_split("/\r\n|\n|\r/", $export);
		$array = preg_replace(["/\s*array\s\($/", "/\)(,)?$/", "/\s=>\s$/"], [NULL, ']$1', ' => ['], $array);
		$export = join(PHP_EOL, array_filter(["["] + $array));
		if((bool)$return)
			return $export;
		else echo $export;
	}

	/**
	 * Return text strings in a "raw" format.
	 * Include settings for further customisation.
	 *
	 * Set crop to TRUE, the output will be cropped.
	 * Set a language, the string will be formatted with PrismJS.
	 *
	 * @param string|array $str
	 * @param array|null   $settings
	 *
	 * @return string
	 */
	public static function pre($str, ?array $settings = [])
	{
		if(is_array($str)){
			$str = str::var_export($str, true);
		}

		extract($settings);

		if($crop){
			return /** @lang HTML */ <<<EOF
			<xmp style="
				font-size: x-small;
				white-space: normal;
				word-break: normal;
				background: none;
				border: none;
				text-align: left;
				color: inherit;
				margin-bottom: -10px;
				line-height:12px;
				white-space:pre-wrap; word-wrap:break-word;
				max-height:80vh;
			">$str</xmp>
EOF;
		}

		$Parsedown = new \Parsedown();
		$Parsedown->setSafeMode(true);
		$str = "```\r\n{$str}\r\n```";
		$str = $Parsedown->text($str);

		# Parent class
		$parent_class_array = str::getAttrArray($parent_class, "code-mute", $only_parent_class);
		$parent_class = str::getAttrTag("class", $parent_class_array);

		# Parent style
		$parent_style = str::getAttrTag("style", $parent_style);

		if($language){
			# Class
			$class_array = str::getAttrArray($class, "language-{$language}", $only_class);
			$class = str::getAttrTag("class", $class_array);

			# Style
			$style = str::getAttrTag("style", $style);
			$str = str_replace("<code>", "<code{$class}{$style}>", $str);
		}

		$str = "<div{$parent_class}{$parent_style}>{$str}</div>";

		return $str;
	}

	/**
	 * Reposition an array element by its key.
	 *
	 * @param array      $array The array being reordered.
	 * @param string|int $key   They key of the element you want to reposition.
	 * @param int        $order The position in the array you want to move the element to. (0 is first)
	 */
	static function repositionArrayElement(array &$array, $key, int $order): void
	{
		if(($a = array_search($key, array_keys($array))) === false){
			return;
		}
		$p1 = array_splice($array, $a, 1);
		$p2 = array_splice($array, 0, $order);
		$array = array_merge($p2, $p1, $array);
	}

	/**
	 * Orders a multidimensional array by one or many
	 * key values.
	 *
	 * Maintains the key values, unless told otherwise.
	 *
	 * <code>
	 * $order = [
	 *    "countryCode" => "asc",
	 *    "stateProv" => "asc",
	 *    "city" => "asc",
	 *    "addr1" => "asc",
	 * ];
	 * </code>
	 * @param array|null   $array          $array
	 * @param array|string $order
	 * @param bool|null    $reset_keys     If set to TRUE, will reset the root keys to match the new order.
	 * @param bool|null    $case_sensitive If set to TRUE, will sort case-sensitive. Uppercase will have priority over
	 *                                     lowercase.
	 *
	 * @link https://stackoverflow.com/a/9261304/429071
	 */
	static function multidimensionalOrderBy(?array &$array, $order = ["order" => "ASC"], ?bool $reset_keys = NULL, ?bool $case_sensitive = NULL): void
	{
		if(!$array){
			return;
		}

		if(!is_array($order)){
			$order = [$order => 'asc'];
		}

		uasort($array, function($a, $b) use ($order, $case_sensitive){
			$t = [true => -1, false => 1];
			$r = true;
			$k = 1;
			foreach($order as $key => $value){
				if(is_array($value)){
					continue;
				}
				$k = (mb_strtolower($value) === 'asc') ? 1 : -1;

				$a_value = self::getNestedValue($a, $key);
				$b_value = self::getNestedValue($b, $key);

				if($case_sensitive || is_array($a_value) || is_array($b_value)){
					$r = ($a_value < $b_value);
				}
				else {
					$r = (strtolower($a_value) < strtolower($b_value));
				}
				if($a_value !== $b_value){
					return $t[$r] * $k;
				}
			}
			return $t[$r] * $k;
		});

		if($reset_keys){
			$array = array_values($array);
		}
	}

	/**
	 * @param array $array
	 * @param       $keyPath
	 *
	 * @return array|mixed|null
	 */
	private static function getNestedValue(array $array, $keyPath)
	{
		if(is_array($keyPath)){
			$keys = $keyPath;
		}
		else {
			$keys = explode('.', $keyPath);
		}
		foreach($keys as $key){
			if(!isset($array[$key])){
				return NULL;
			}
			$array = $array[$key];
		}
		return $array;
	}


	/**
	 * Returns FALSE is array is numeric (sequential, 0 to n row keys), TRUE otherwise.
	 * @link https://stackoverflow.com/a/173479/429071
	 *
	 * @param array $arr
	 *
	 * @return bool
	 */
	static function isAssociativeArray($arr)
	{
		if(!is_array($arr))
			return false;
		if([] === $arr)
			return false;
		return array_keys($arr) !== range(0, count($arr) - 1);
	}

	/**
	 * Checks to see whether an array has sequential numerical keys (only),
	 * starting from 0 to n, where n is the array count minus one.
	 * The opposite of `isAssociativeArray()`.
	 * @link https://codereview.stackexchange.com/questions/201/is-numeric-array-is-missing/204
	 *
	 * @param $arr
	 *
	 * @return bool
	 */
	static function isNumericArray($arr)
	{
		if(!is_array($arr))
			return false;
		return array_keys($arr) === range(0, (count($arr) - 1));
	}

	/**
	 * Returns the given string with an added number suffix.
	 * If the string already has a number suffix, will add one
	 * step to that number.
	 *
	 * @param string|null $string
	 * @param int|null    $start
	 * @param int|null    $step
	 *
	 * @return string|null
	 */
	public static function addNumberSuffix(?string $string, ?int $start = 2, ?int $step = 1): ?string
	{
		if(!$string){
			return $string;
		}

		if(!preg_match("/^(.*?)\s\(([0-9]+)\)$/", $string, $match)){
			//if the string doesn't have a number suffix
			return "{$string} ({$start})";
		}

		# Grab the number, and increase it by one step
		$number = $match[2] + $step;

		# Return the string and the new number suffix
		return "{$match[1]} ({$number})";
	}

	/**
	 * Given an array, returns a human readable XML string.
	 *
	 * @param $array array A standard PHP array that you want to convert to XML.
	 * @param $root  string The root bracket that you want to enclose the XML in.
	 *
	 * @return string A human readable XML string.
	 */
	public static function xmlify($array, $root)
	{
		$xml = self::array_to_xml($array, new \SimpleXMLElement($root))->asXML();
		$dom = new \DOMDocument;
		$dom->preserveWhiteSpace = false;
		$dom->loadXML($xml);
		$dom->formatOutput = true;
		$xml_string = $dom->saveXml($dom->documentElement);
		return $xml_string;
	}

	/**
	 * Returns an array as a CSV string with the keys as a header row.
	 *
	 * @param $array
	 *
	 * @return bool
	 */
	public static function get_array_as_csv($array)
	{
		$f = fopen('php://memory', 'r+');
		# Header row
		fputcsv($f, array_keys(array_shift($array)));
		# Data rows
		foreach($array as $fields){
			fputcsv($f, $fields);
		}
		rewind($f);
		$csv_line = stream_get_contents($f);
		return rtrim($csv_line);
	}

	/**
	 * Given the file name of a CSV file,
	 * returns an array with the values, where each row's keys
	 * are the CSV column names.
	 *
	 * @param string      $file
	 * @param string|null $delimiter
	 * @param int|null    $skip_rows
	 *
	 * @return array
	 */
	public static function csvAsArray(string $file, ?string $delimiter = ",", ?int $skip_rows = 0): array
	{
		$all_rows = [];

		$header = NULL;//null; // has header

		if(($handle = fopen($file, "r")) !== false){
			while(($row = fgetcsv($handle, 1000, $delimiter)) !== false) {
				if($skip_rows && $skip_rows > $rows_skipped){
					$rows_skipped++;
					continue;
				}
				if($header === NULL){
					$header = $row;
					continue;
				}
				$all_rows[] = array_combine($header, $row);
			}

			fclose($handle);
		}
		return $all_rows;
	}

	/**
	 * Converts an external CSV file, internal CSV file, or CSV data to an array.
	 *
	 * @param string      $data
	 * @param bool|null   $has_header_row
	 * @param string|null $separator
	 * @param string|null $enclosure
	 * @param string|null $escape
	 *
	 * @return array|null
	 */
	public static function convertCsvToArray(string $data, ?bool $has_header_row = NULL, ?string $separator = NULL, ?string $enclosure = NULL, ?string $escape = NULL, ?string $null = NULL): ?array
	{
		# External file
		if(filter_var($data, FILTER_VALIDATE_URL) !== false){
			$f = new \SplFileObject($data);
		}

		# Internal file
		else if(file_exists($data)){
			$f = new \SplFileObject($data);
		}

		# Data string (that needs to be stored as a local file)
		else {
			# Generate a random tmp name
			$tmp_name = $_ENV['tmp_dir'] . str::id("csv");

			# Store the data as a file
			file_put_contents($tmp_name, $data);

			# Load the file as an object
			$f = new \SplFileObject($tmp_name);
		}

		# Convert text to actual
		foreach(["separator", "enclosure", "escape"] as $key){
			${$key} = str_replace(["\\t", "\\r\\n", "\\r", "\\n"], ["\t", "\r\n", "\r", "\n"], ${$key});
		}

		$f->setFlags(\SplFileObject::READ_CSV
			| \SplFileObject::SKIP_EMPTY
			| \SplFileObject::READ_AHEAD
			| \SplFileObject::DROP_NEW_LINE
		);
		$f->setCsvControl($separator, $enclosure, $escape);

		foreach($f as $row_number => $row){
			# Trim all cells
			foreach($row as $id => $cell){
				$row[$id] = trim($cell);

				# Convert empty strings to NULL
				if($null){
					if($row[$id] === $null){
						$row[$id] = NULL;
					}
				}
			}

			if(!$row_number && $has_header_row){
				$header = $row;

				# Suffix non-unique headers with their corresponding Excel column key
				if(count($header) != count(array_unique($header))){
					$counts = array_count_values($header);
					foreach($header as $id => $title){
						if($counts[$title] > 1){
							$header[$id] = "{$title} [" . str::excelKey($id) . "]";
						}
					}
				}
				continue;
			}

			if(!$header){
				$rows[] = $row;
				continue;
			}

			$rows[] = array_combine($header, $row);
		}

		# Clear the temporary file if one was used
		if($tmp_name){
			unlink($tmp_name);
		}

		return $rows;
	}

	/**
	 * Returns the input with a "A" or "AN" depending on the word.
	 * Generally ,if the word starts with a vowel, "AN" is used,
	 * but there are exceptions.
	 *
	 * @param string|null $input
	 * @param bool|null   $include_word
	 *
	 * @return string|null
	 */
	public static function A(?string $input, ?bool $include_word = true): ?string
	{
		if(!$input = trim(strip_tags($input))){
			return NULL;
		}

		# Filter out the word in case there are more than one
		preg_match("/\A(\s*)(?:an?\s+)?(.+?)(\s*)\Z/i", $input, $matches);

		# Break up the matches array into its constituent parts
		[$all, $pre, $word, $post] = $matches;

		# Ensure we have a word to work with
		if(!$word){
			//if there is no word
			return $input;
		}

		# Get the indefinite article
		$result = self::getIndefiniteArticle($word, true);
		// For some reason, we have to include word or else it gets it wrong

		if(!$include_word){
			$result = trim(str_replace($word, "", $result));
		}

		return $pre . $result . $post;
	}

	/**
	 * This pattern matches strings of capitals starting with a "vowel-sound"
	 * consonant followed by another consonant, and which are not likely
	 * to be real words (oh, all right then, it's just magic!).
	 *
	 * @var string
	 */
	private static $A_abbrev = "(?! FJO | [HLMNS]Y.  | RY[EO] | SQU
		  | ( F[LR]? | [HL] | MN? | N | RH? | S[CHKLMNPTVW]? | X(YL)?) [AEIOU])
			[FHLMNRSX][A-Z]
		";

	/**
	 * This pattern codes the beginnings of all english words beginning with a
	 * 'y' followed by a consonant. Any other y-consonant prefix therefore
	 * implies an abbreviation.
	 *
	 * @var string
	 */
	private static $A_y_cons = 'y(b[lor]|cl[ea]|fere|gg|p[ios]|rou|tt)';


	/**
	 * Exceptions to exceptions.
	 *
	 * @var string
	 */
	private static $A_explicit_an = "euler|hour(?!i)|heir|honest|hono|mot";

	private static $A_ordinal_an = "[aefhilmnorsx]-?th";

	private static $A_ordinal_a = "[bcdgjkpqtuvwyz]-?th";

	/**
	 * Given a word, will return the indefinite article,
	 * which will either be "a" or "an", optionally followed
	 * by the word.
	 *
	 * This method should not be called directly, and instead the
	 * `str::A()` method should be called.
	 *
	 * @param string|null $word         The word to find the related indefinite article to
	 * @param bool        $include_word If set, will include the word also
	 *
	 * @return string
	 */
	private static function getIndefiniteArticle(?string $word, bool $include_word): string
	{
		#any number starting with an '8' uses 'an'
		if(preg_match("/^[8](\d+)?/", $word))
			return $include_word ? "an {$word}" : "an";

		#numbers starting with a '1' are trickier, only use 'an'
		#if there are 3, 6, 9, … digits after the 11 or 18

		#check if word starts with 11 or 18
		if(preg_match("/^[1][1](\d+)?/", $word) || (preg_match("/^[1][8](\d+)?/", $word))){

			#first strip off any decimals and remove spaces or commas
			#then if the number of digits modulus 3 is 2 we have a match
			if(strlen(preg_replace(["/\s/", "/,/", "/\.(\d+)?/"], '', $word)) % 3 == 2)
				return $include_word ? "an {$word}" : "an";
		}

		# HANDLE ORDINAL FORMS
		if(preg_match("/^(" . self::$A_ordinal_a . ")/i", $word))
			return $include_word ? "a {$word}" : "an";
		if(preg_match("/^(" . self::$A_ordinal_an . ")/i", $word))
			return $include_word ? "an {$word}" : "an";

		# HANDLE SPECIAL CASES

		if(preg_match("/^(" . self::$A_explicit_an . ")/i", $word))
			return $include_word ? "an {$word}" : "an";
		if(preg_match("/^[aefhilmnorsx]$/i", $word))
			return $include_word ? "an {$word}" : "an";
		if(preg_match("/^[bcdgjkpqtuvwyz]$/i", $word))
			return $include_word ? "a {$word}" : "an";

		# HANDLE ABBREVIATIONS

		if(preg_match("/^(" . self::$A_abbrev . ")/x", $word))
			return $include_word ? "an {$word}" : "an";
		if(preg_match("/^[aefhilmnorsx][.-]/i", $word))
			return $include_word ? "an {$word}" : "an";
		if(preg_match("/^[a-z][.-]/i", $word))
			return $include_word ? "a {$word}" : "an";

		# HANDLE CONSONANTS

		#KJBJM - the way this is written it will match any digit as well as non vowels
		#But is necessary for later matching of some special cases.  Need to move digit
		#recognition above this.
		#rule is: case insensitive match any string that starts with a letter not in [aeiouy]
		if(preg_match("/^[^aeiouy]/i", $word))
			return $include_word ? "a {$word}" : "an";

		# HANDLE SPECIAL VOWEL-FORMS

		if(preg_match("/^e[uw]/i", $word))
			return $include_word ? "a {$word}" : "an";
		if(preg_match("/^onc?e\b/i", $word))
			return $include_word ? "a {$word}" : "an";
		if(preg_match("/^uni([^nmd]|mo)/i", $word))
			return $include_word ? "a {$word}" : "an";
		if(preg_match("/^ut[th]/i", $word))
			return $include_word ? "an {$word}" : "an";
		if(preg_match("/^u[bcfhjkqrst][aeiou]/i", $word))
			return $include_word ? "a {$word}" : "an";

		# HANDLE SPECIAL CAPITALS

		if(preg_match("/^U[NK][AIEO]?/", $word))
			return $include_word ? "a {$word}" : "an";

		# HANDLE VOWELS

		if(preg_match("/^[aeiou]/i", $word))
			return $include_word ? "an {$word}" : "an";

		# HANDLE y... (BEFORE CERTAIN CONSONANTS IMPLIES (UNNATURALIZED) "i.." SOUND)

		if(preg_match("/^(" . self::$A_y_cons . ")/i", $word))
			return $include_word ? "an {$word}" : "an";

		#DEFAULT CONDITION BELOW
		# OTHERWISE, GUESS "a"
		return $include_word ? "a {$word}" : "an";
	}

	/**
	 * Flatten multi-dimensional array.
	 *
	 * @param mixed|null  $array $array The array to flatten
	 * @param string|null $glue  The glue to use in the flattened array keys. Default is dot-notation.
	 *
	 * @return array|NULL
	 * @link https://stackoverflow.com/a/10424516/429071
	 */
	public static function flatten($array, ?string $glue = '.')
	{
		if(!is_array($array)){
			return $array;
		}

		$ritit = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($array));

		$result = [];

		foreach($ritit as $leafValue){
			$keys = [];
			foreach(range(0, $ritit->getDepth()) as $depth){
				$keys[] = $ritit->getSubIterator($depth)->key();
			}
			$result[implode($glue, $keys)] = $leafValue;
		}

		return $result;
	}

	/**
	 * Truncates a given string if it's longer than the max length.
	 * If the last characters are spaces, will trim the spaces.
	 * Will also add a suffix of your choosing. The default is an ellipsis.
	 *
	 * @param string|null $string $string
	 * @param int|null    $max_length
	 * @param string|null $suffix
	 *
	 * @return string|null
	 */
	public static function truncateIf(?string $string, ?int $max_length = 50, ?string $suffix = "..."): ?string
	{
		if(!$string){
			return $string;
		}

		if(mb_strlen($string) <= $max_length){
			return $string;
		}

		return trim(mb_substr($string, 0, $max_length)) . $suffix;
	}

	/**
	 * Searches through a multi-dimensional array for a key,
	 * and returns an array of values belonging to matching keys.
	 *
	 * @param $array
	 * @param $key
	 *
	 * @return array
	 */
	public static function array_key_search($array, $key): ?array
	{
		$results = [];

		if(!is_array($array)){
			return $results;
		}

		if($array[$key]){
			if(str::isNumericArray($array[$key])){
				$results = array_merge($results, $array[$key]);
			}
			else {
				$results[] = $array[$key];
			}
			unset($array[$key]);
		}

		foreach($array as $k => $v){
			if(is_array($v)){
				$results = array_merge($results, self::array_key_search($v, $key));
			}
		}

		return $results;
	}

	/**
	 * Returns all the keys of the values that match $needle in $haystack.
	 * Same as array_search, but returns *all* the hits, not just the first one.
	 *
	 * @param       $needle
	 * @param array $haystack
	 *
	 * @return array|null
	 * @link https://www.php.net/manual/en/function.array-search.php#88465
	 */
	public static function array_search_all($needle, array $haystack): ?array
	{
		foreach($haystack as $k => $v){

			if($haystack[$k] == $needle){

				$array[] = $k;
			}
		}
		return $array;
	}

	/**
	 * Given an array and a set of `old => new` keys,
	 * will recursively replace all array keys that
	 * are old with their corresponding new value.
	 *
	 * Updated from
	 * @link https://programmer.group/php-recursive-multidimensional-array-replacement-key-name-and-value.html
	 *
	 * @param mixed $array
	 * @param array $old_to_new_keys
	 *
	 * @return array
	 */
	public static function array_replace_keys($array, array $old_to_new_keys)
	{
		if(!is_array($array)){
			return $array;
		}

		$temp_array = [];
		$ak = array_keys($old_to_new_keys);
		$av = array_values($old_to_new_keys);

		foreach($array as $key => $value){
			if(array_search($key, $ak, true) !== false){
				$key = $av[array_search($key, $ak)];
			}

			if(is_array($value)){
				$value = str::array_replace_keys($value, $old_to_new_keys);
			}

			$temp_array[$key] = $value;
		}

		return $temp_array;
	}

	/**
	 * Given a multidimensional array, replaces all old values with new ones
	 * from the `old => new` array.
	 *
	 * @param mixed $array
	 * @param array $old_to_new_values
	 *
	 * @return array
	 * @link https://programmer.group/php-recursive-multidimensional-array-replacement-key-name-and-value.html
	 */
	public static function array_replace_values($array, ?array $old_to_new_values)
	{
		if(!is_array($array)){
			return $array;
		}

		if(!is_array($old_to_new_values)){
			return $array;
		}

		$temp_array = [];
		$ak = array_keys($old_to_new_values);
		$av = array_values($old_to_new_values);

		foreach($array as $key => $value){
			if(is_array($value)){
				$value = str::array_replace_values($value, $old_to_new_values);
			}
			else {
				if(array_search($value, $ak, true) !== false){
					$value = $av[array_search($value, $ak)];
				}
			}

			$temp_array[$key] = $value;
		}
		return $temp_array;
	}

	public static function array_intersect_reverse(array $arr1, array $arr2): array
	{
		$diff1 = array_diff($arr1, $arr2);
		$diff2 = array_diff($arr2, $arr1);
		return array_filter(array_merge($diff1 ?: [], $diff2 ?: []));
	}


	/**
	 * Will return all the keys for rows that contain a match in the $col for any of the $value.
	 *
	 * @param array|null  $array
	 * @param string|null $col
	 * @param array|null  $values
	 *
	 * @return array|null
	 */
	public static function array_intersect_key_multi_values(?array $array, ?string $col, ?array $values): ?array
	{
		if(empty($array)){
			return NULL;
		}
		if(!$col){
			return NULL;
		}
		if(empty($values)){
			return NULL;
		}

		$matches = [];

		foreach($values as $value){
			$matches = array_merge($matches, array_keys(array_column($array, $col), $value));
		}
		return $matches;
	}

	/**
	 * Returns the difference between two multidimensional arrays.
	 * Useful to ascertain what has changed between two versions of a hit.
	 *
	 * @param array $array1
	 * @param array $array2
	 *
	 * @return array
	 */
	public static function array_diff_multidimensional(array $array1, array $array2): array
	{
		$difference = [];

		foreach($array1 as $key => $value){
			/**
			 * For values that are numerical arrays, we replace the numerical keys with hashes.
			 * This is to ensure that the comparison is accurate, and not affected by the order
			 * of the array.
			 */
			if(str::isNumericArray($value)){
				$value_hashes = array_map(function($v){
					return str::getHash($v);
				}, $value);

				# Replace the numerical keys with the hashes
				$value = array_combine($value_hashes, $value);

				if(!isset($array2[$key]) || !is_array($array2[$key])){
					$difference[$key] = $value;
					continue;
				}
				$array2_hashes = array_map(function($v){
					return str::getHash($v);
				}, $array2[$key]);

				# Replace the numerical keys with the hashes
				$array2[$key] = array_combine($array2_hashes, $array2[$key]);
			}

			# If the value is an array, go deeper and compare the arrays
			if(is_array($value)){
				# Unless the value key isn't set, in which case, we don't need to compare
				if(!isset($array2[$key]) || !is_array($array2[$key])){
					$difference[$key] = $value;
					continue;
				}

				# Get and differences in the values arrays
				$new_diff = self::array_diff_multidimensional($value, $array2[$key]);

				# If there are differences, add them to the difference array
				if(!empty($new_diff)){
					$difference[$key] = $new_diff;
				}

				continue;
			}

			# If the value is not an array, compare the values
			if(!array_key_exists($key, $array2) || $array2[$key] !== $value){
				$difference[$key] = $value;
			}
		}

		return $difference;
	}

	/**
	 * Same as array unique, but works on multi-dimensional arrays.
	 *
	 * @param array $input
	 *
	 * @return array
	 * @link https://stackoverflow.com/a/308955/429071
	 */
	public static function array_unique_multidimensional(array $input): array
	{
		$serialized = array_map('serialize', $input);
		$unique = array_unique($serialized);
		return array_intersect_key($input, $unique);
	}

	/**
	 * Given an int number, returns the corresponding Excel-style
	 * column name, 0 = A, 1 = B, 26 = AA, 27 = AB, etc.
	 *
	 * @param int $n
	 *
	 * @return string
	 */
	public static function excelKey(int $n): string
	{
		for($r = ""; $n >= 0; $n = intval($n / 26) - 1)
			$r = chr($n % 26 + 0x41) . $r;
		return $r;
	}

	/**
	 * Given (whole) seconds, returns the equivalent with the
	 * following format: N hrs, N min, N sec.
	 *
	 * @param int|null $seconds
	 *
	 * @return string|null
	 * @link https://stackoverflow.com/a/34681477/429071
	 */
	public static function getHisFromS(?float $seconds = NULL): ?string
	{
		if($seconds === NULL){
			return NULL;
		}

		# In case a float is passed, round it
		$seconds = round($seconds);

		$dt = new \DateTime("1970-01-01 {$seconds} seconds");

		return ((int)$dt->format('H')) . " hr, " . ((int)$dt->format('i')) . " min, " . ((int)$dt->format('s')) . " sec";
	}

	/**
	 * Given an array, will create a string from the values, with the glue as the glue.
	 *
	 * @param mixed       $array
	 * @param string|null $glue
	 *
	 * @return string|null
	 */
	public static function stringFromArray($array, ?string $glue = "|"): ?string
	{
		# If it's missing, return NULL
		if(!$array){
			return NULL;
		}

		# If it's not an array, just return it
		if(!is_array($array)){
			return $array;
		}

		return implode($glue, $array);
	}

	/**
	 * Starts a "timer". Returns the time started.
	 *
	 * @return float
	 */
	public static function startTimer(): float
	{
		return microtime(true);
	}

	/**
	 * Stops a "timer". Returns the seconds passed since
	 * the start time given. If no start time is given,
	 * will start from when the request handling was first
	 * started.
	 *
	 * @param float|null $start_time
	 *
	 * @return float
	 */
	public static function stopTimer(?float $start_time = NULL, ?string $marker = NULL): float
	{
		$now = microtime(true);

		if(!$start_time){
			global $request_start_time;
			$start_time = $request_start_time;
		}

		$stop_time = round($now - $start_time, 3);

		if($marker){
			echo "{$marker}: $stop_time" . PHP_EOL;
		}

		return $stop_time;
	}

	/**
	 * Same as exec(), except it will terminate the process
	 * if it takes longer than the timeout.
	 *
	 * Will notify admins if there is an error with the command.
	 *
	 * @param string     $command
	 * @param array|null $output
	 * @param int|null   $timeout
	 *
	 * @return bool Returns TRUE on success or FALSE on failure.
	 */
	public static function exec(string $command, ?array &$output = [], ?int $timeout = 30): bool
	{
		$descriptorspec = [
			0 => ["pipe", "r"],  // stdin
			1 => ["pipe", "w"],  // stdout
			2 => ["pipe", "w"],   // stderr
			3 => ["pipe", "w"],   // stderr (for the return value)
		];

		$process = proc_open("{$command};echo $? >&3", $descriptorspec, $pipes);
		// We're adding the echo $? >&3 to get the return value of the command

		if(is_resource($process)){
			// Wait for the process to terminate or the timeout to expire
			$endTime = time() + $timeout;
			while(time() < $endTime && $status = proc_get_status($process)) {
				if(!$status['running']){
					break; // Process finished before timeout
				}
				usleep(100000); // Sleep for 0.1 seconds
			}

			if($status['running']){
				// The process is still running, so terminate it
				proc_terminate($process);
				return false;
			}

			else {
				// Process completed, read its output

				# Get the pipe metadata to ensure the pipes are not blocked
				$meta_data[1] = stream_get_meta_data($pipes[1]);
				$meta_data[2] = stream_get_meta_data($pipes[2]);

				if($meta_data[1]['blocked']){
					//unblock the stdout stream
					stream_set_blocking($pipes[1], 0);
				}

				if($meta_data[2]['blocked']){
					//unblock the stderr stream
					stream_set_blocking($pipes[2], 0);
				}
				// This will the pipes hanging

				$stdout = stream_get_contents($pipes[1]);
				$stderr = stream_get_contents($pipes[2]);

				# Get the return value of the command
				$return_value = (int)rtrim(fgets($pipes[3], 5), "\n");
			}

			// Close all pipes and terminate the process
			fclose($pipes[0]);
			fclose($pipes[1]);
			fclose($pipes[2]);
			fclose($pipes[3]);

			proc_close($process);
		}

		if($stdout){
			$output = explode(PHP_EOL, $stdout);
		}

		if($stderr){
			$output = explode(PHP_EOL, $stderr);
		}

		# If there is an error, notify admins
		if($return_value !== 0){
			# Write the message
			$title = "Command failed";
			$message = "<p>Command: <pre>{$command}</pre></p>
			<p>Output: <pre>" . implode("\n", $output) . "</pre></p>
			<p>Return value: {$return_value}</p>";

			# Log it
			Log::getInstance()->error([
				"display" => false,
				"title" => $title,
				"message" => $message,
			]);

			# Email admins
			Email::notifyAdmins([
				"subject" => $title,
				"body" => $message,
				"backtrace" => str::backtrace(true, false),
			]);
			return false;
		}

		return true;
	}

	/**
	 * Returns an array of information about a given process ID.
	 * If the process ID is not found, returns NULL.
	 *
	 * Array contains the following keys:
	 * - PID (process ID)
	 * - COMMAND (command)
	 * - ELAPSED (elapsed time)
	 * - START_TIME (start time in UTC)
	 * - USER (user)
	 * - %CPU (CPU usage)
	 * - %MEM (memory usage)
	 * - VSZ (virtual memory size)
	 * - RSS (resident set size)
	 * - TT (terminal)
	 * - STAT (status)
	 * - WCHAN (wait channel)
	 *
	 * @param string $pid
	 *
	 * @return array|null
	 */
	public static function getPidInfo(string $pid): ?array
	{
		// Command to execute
		$command = "ps -p $pid -o pid,comm,etime,user,pcpu,pmem,vsz,rss,tty,stat,wchan";

		// Array to store the output
		$output = [];

		// Execute the command
		str::exec($command, $output);

		// Remove and process the first line (column headers)
		$headers = preg_split('/\s+/', trim(array_shift($output)));

		// Initialize an associative array
		$pid_info = [];

		// Process the remaining lines
		foreach($output as $line){
			# Ignore empty lines
			if(!$line){
				continue;
			}

			// Split the line into columns
			$columns = preg_split('/\s+/', trim($line), -1, PREG_SPLIT_NO_EMPTY);

			// Combine headers and columns to form an associative array
			$pid_info[] = array_combine($headers, $columns);
		}

		if(!$pid_info){
			return NULL;
		}

		# There will only ever be one process
		$pid_info = reset($pid_info);

		# If the elapsed time includes days, we need to split it up
		if(strpos($pid_info['ELAPSED'], "-")){
			[$days, $time] = explode('-', $pid_info['ELAPSED']);
		}

		# If the elapsed time only includes one :, then it's hours and minutes
		else if(substr_count($pid_info['ELAPSED'], ':') == 1){
			$days = 0;
			$time = "00:" . $pid_info['ELAPSED'];
		}

		# Otherwise, just set the days to 0
		else {
			$days = 0;
			$time = $pid_info['ELAPSED'];
		}

		# Break up the time into hours, minutes, and seconds
		[$hours, $minutes, $seconds] = explode(':', $time);

		# Create a DateInterval object for the elapsed time
		$interval = new \DateInterval("P{$days}DT{$hours}H{$minutes}M{$seconds}S");

		# Subtract the interval from the current time
		$startTime = new \DateTime();
		$startTime->sub($interval);

		# Add the start time to the process info
		$pid_info['START_TIME'] = $startTime->format('Y-m-d H:i:s');

		return $pid_info;
	}

	public static function marker(?string $marker = "Marker", ?bool $prod_enable = NULL): void
	{
		if(!$prod_enable && !str::isDev()){
			return;
		}

		echo str::title($marker) . " " . str::stopTimer() . PHP_EOL;
	}

	/**
	 * Given a string, will break it apart by the delimiter and return an array.
	 *
	 * @param mixed       $string
	 * @param string|null $delimiter
	 *
	 * @return array|null
	 */
	public static function arrayFromString($string, ?string $delimiter = "|"): ?array
	{
		# If it's already an array, just return it
		if(is_array($string)){
			# Remove empty values
			return array_filter($string, function($value){
				return (bool)strlen($value);
			});
		}

		# If it's missing, return NULL
		if(!strlen($string)){
			return NULL;
		}

		return explode($delimiter, $string);
	}

	/**
	 * A safe way of converting an array to JSON.
	 * Will take into consideration that binary strings
	 * cannot be converted to JSON, and will convert
	 * them to NULL values or base64 encoded strings.
	 *
	 * @param mixed|null  $data
	 * @param string|null $conversion_type Options are "null" or "base64". Default is "null".
	 *
	 * @return false|string
	 */
	public static function json_encode($data, ?string $conversion_type = NULL, $flags = NULL)
	{
		$data = self::convertToJsonFriendlyValue($data, $conversion_type);
		return json_encode($data, $flags);
	}

	/**
	 * @param mixed|null  $value
	 * @param string|null $conversion_type
	 *
	 * @return array|mixed|string|null
	 */
	private static function convertToJsonFriendlyValue($value, ?string $conversion_type = NULL)
	{
		if(is_array($value)){
			$value = array_map(function($value){
				return self::convertToJsonFriendlyValue($value);
			}, $value);
		}

		else if(is_object($value)){
			$value = (array)$value;
			$value = array_map(function($value){
				return self::convertToJsonFriendlyValue($value);
			}, $value);
		}

		else if(str::isBinary($value)){
			switch($conversion_type) {
			case 'base64':
				$value = base64_encode($value);
				break;
			case 'null':
			default:
				$value = NULL;
				break;
			}
		}

		return $value;
	}

	public static function isBinary(?string $data): bool
	{
		if(!$data){
			return false;
		}
		return !mb_check_encoding($data, 'UTF-8');
	}

	/**
	 * Takes a JSON string and converts it into an array.
	 * If the JSON is already an array, returns the array.
	 *
	 * @param $json
	 *
	 * @return array|null
	 */
	public static function arrayFromJsonString($json): ?array
	{
		if(!$json){
			return NULL;
		}

		if(is_array($json)){
			return $json;
		}

		if(!str::isJson($json)){
			return [$json];
		}

		$decoded = json_decode($json, true);

		# Will always return an array from JSON data
		return is_array($decoded) ? $decoded : [$decoded];
	}

	/**
	 * Does the leg work deciding "[1 ]thing was ", or "[2 ]things were ".
	 *
	 * @param array|int   $array         An array of things that will be counted, or an int of the count.
	 * @param string|null $rel_table     The name of the thing that is to be counted.
	 * @param bool        $include_count If set to true, will include the count also.
	 *
	 * @return bool|mixed Returns a string if the vars have been entered correctly, otherwise FALSE.
	 */
	public static function were($array, ?string $rel_table = NULL, ?bool $include_count = NULL): string
	{
		# Get the count
		if(is_array($array)){
			$count = count($array);
		}
		else if(is_int($array) || is_string($array)){
			$count = (int)$array;
		}
		else {
			$count = 0;
		}

		# Set the subject
		$subject = $count == 1 ? $rel_table : str::pluralise($rel_table);

		# Set the verb
		$verb = $count == 1 ? "was" : "were";

		# Set the number
		$number = $count ?: "no";

		# Decide between "no and "No"
		if($rel_table == ucwords($rel_table)){
			$number = ucwords($number);
		}

		# If the count is also be included
		if($include_count){
			return "{$number} {$subject} {$verb}";
		}

		# Otherwise, just return subject verb
		return "{$subject} {$verb}";
	}

	static $plural = [
		'/(quiz)$/i' => "$1zes",
		'/^(ox)$/i' => "$1en",
		'/([m|l])ouse$/i' => "$1ice",
		'/(matr|vert|ind)ix|ex$/i' => "$1ices",
		'/(x|ch|ss|sh)$/i' => "$1es",
		'/([^aeiouy]|qu)y$/i' => "$1ies",
		'/(hive)$/i' => "$1s",
		'/(?:([^f])fe|([lr])f)$/i' => "$1$2ves",
		'/(shea|lea|loa|thie)f$/i' => "$1ves",
		'/sis$/i' => "ses",
		'/([ti])um$/i' => "$1a",
		'/(tomat|potat|ech|her|vet)o$/i' => "$1oes",
		'/(bu)s$/i' => "$1ses",
		'/(alias)$/i' => "$1es",
		'/(octop)us$/i' => "$1i",
		'/(ax|test)is$/i' => "$1es",
		'/(us)$/i' => "$1es",
		'/s$/i' => "s",
		'/$/' => "s",
	];

	static $singular = [
		'/(quiz)zes$/i' => "$1",
		'/(matr)ices$/i' => "$1ix",
		'/(vert|ind)ices$/i' => "$1ex",
		'/^(ox)en$/i' => "$1",
		'/(alias)es$/i' => "$1",
		'/(octop|vir)i$/i' => "$1us",
		'/(cris|ax|test)es$/i' => "$1is",
		'/(shoe)s$/i' => "$1",
		'/(o)es$/i' => "$1",
		'/(bus)es$/i' => "$1",
		'/([m|l])ice$/i' => "$1ouse",
		'/(x|ch|ss|sh)es$/i' => "$1",
		'/(m)ovies$/i' => "$1ovie",
		'/(s)eries$/i' => "$1eries",
		'/([^aeiouy]|qu)ies$/i' => "$1y",
		'/([lr])ves$/i' => "$1f",
		'/(tive)s$/i' => "$1",
		'/(hive)s$/i' => "$1",
		'/(li|wi|kni)ves$/i' => "$1fe",
		'/(shea|loa|lea|thie)ves$/i' => "$1f",
		'/(^analy)ses$/i' => "$1sis",
		'/((a)naly|(b)a|(d)iagno|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/i' => "$1$2sis",
		'/([ti])a$/i' => "$1um",
		'/(n)ews$/i' => "$1ews",
		'/(h|bl)ouses$/i' => "$1ouse",
		'/(corpse)s$/i' => "$1",
		'/(us)es$/i' => "$1",
		'/s$/i' => "",
	];

	static $irregular = [
		'move' => 'moves',
		'foot' => 'feet',
		'goose' => 'geese',
		'sex' => 'sexes',
		'child' => 'children',
		'man' => 'men',
		'tooth' => 'teeth',
		'person' => 'people',
		'valve' => 'valves',
	];

	static $uncountable = [
		'sheep',
		'fish',
		'deer',
		'series',
		'species',
		'money',
		'rice',
		'information',
		'equipment',
	];

	/**
	 * Pluralises a string.
	 *
	 * @param $string
	 *
	 * @return bool|string
	 * @link http://kuwamoto.org/2007/12/17/improved-pluralizing-in-php-actionscript-and-ror/
	 */
	public static function pluralise($string)
	{
		// If no string is supplied
		if(!$string){
			return false;
		}

		// save some time in the case that singular and plural are the same
		if(in_array(mb_strtolower($string), self::$uncountable))
			return $string;


		// check for irregular singular forms
		foreach(self::$irregular as $pattern => $result){
			$pattern = '/' . $pattern . '$/i';

			if(preg_match($pattern, $string))
				return preg_replace($pattern, $result, $string);
		}

		// check for matches using regular expressions
		foreach(self::$plural as $pattern => $result){
			if(preg_match($pattern, $string))
				return preg_replace($pattern, $result, $string);
		}

		return $string;
	}

	/**
	 * @param $string
	 *
	 * @return string|string[]|null
	 */
	public static function singularise($string)
	{
		// save some time in the case that singular and plural are the same
		if(in_array(mb_strtolower($string), self::$uncountable))
			return $string;

		// check for irregular plural forms
		foreach(self::$irregular as $result => $pattern){
			$pattern = '/' . $pattern . '$/i';

			if(preg_match($pattern, $string))
				return preg_replace($pattern, $result, $string);
		}

		// check for matches using regular expressions
		foreach(self::$singular as $pattern => $result){
			if(preg_match($pattern, $string))
				return preg_replace($pattern, $result, $string);
		}

		return $string;
	}

	/**
	 * Returns $rel_table pluralised if $array > 1.
	 * If the <code>$include_count</code> flag is set, will also return the count.
	 * Examples:
	 * <code>
	 * $this->str->pluralise_if(3, "chair", true);
	 * //3 chairs
	 * $this->str->pluralise_if(false, "item", true);
	 * //0 items
	 * str::pluralise_if(1, "boat", false);
	 * //boat
	 * </code>
	 *
	 * @param      $array         array|int|string The array to count, or int to check or string to convert to number
	 *                            to check
	 * @param      $rel_table     string The word to pluralise
	 * @param null $include_count bool If set to TRUE, will include the count as a prefix in the string returned.
	 * @param null $include_is_are
	 *
	 * @return bool|null|string|string[]
	 */
	public static function pluralise_if($array, $rel_table, $include_count = NULL, $include_is_are = NULL)
	{
		if(is_array($array)){
			$count = count($array);
		}
		else if(is_int($array) && $array){
			$count = $array;
		}
		else if(is_string($array) && $string = preg_replace("/[^0-9.]/", "", $array)){
			$count = $string;
		}
		else {
			$count = 0;
		}
		if(!is_string($rel_table)){
			return false;
		}
		if($include_count){
			$return = "{$count} ";
		}

		$return .= $count == 1 ? $rel_table : str::pluralise($rel_table);

		if($include_is_are){
			$return .= $count == 1 ? " is" : " are";
		}

		return $return;
	}

	/**
	 * Returns the right verb only.
	 *
	 * Returns "is" or "are", or if the past tense flag is set, "was" or "were" verb only,
	 * depending on whether there is 1 or any other number (including zero) of something.
	 *
	 * @param mixed     $count      Count can be either a number, or an array (in which case it gets counted)
	 * @param bool|null $past_tense If set, replaces is/are with was/were
	 *
	 * @return string
	 */
	public static function isAre($count, ?bool $past_tense = NULL): string
	{
		# Get the count
		$count = is_array($count) ? count($count) : $count;

		# Decide the tense
		if($past_tense){
			return abs($count) == 1 ? "was" : "were";
		}

		return abs($count) == 1 ? "is" : "are";
	}

	/**
	 * Returns the right verb only.
	 *
	 * Returns "has" or "have" depending
	 * on whether there is 1 or any
	 * other number (including zero) of
	 * something.
	 *
	 * @param mixed $count Count can be either a number, or an array (in which case it gets counted)
	 *
	 * @return string
	 */
	public static function hasHave($count): string
	{
		# Get the count
		$count = is_array($count) ? count($count) : $count;

		return abs($count) == 1 ? "has" : "have";
	}

	/**
	 * Given an array, returns an oxford comma separated,
	 * grammatically correct list of items:
	 * <code>
	 * ["apples","oranges","bananas","pears"]
	 * </code>
	 * //Returns "apples, oranges, bananas, and pears"
	 *
	 * @param array|null  $array  An array of items
	 * @param string|null $tag    Must not include the brackets, just the tag name.
	 * @param string|null $and_or "and" or "or", or anything else that will come between the two last items
	 * @param string|null $glue   The glue between each item, default is ", "
	 *
	 * @return bool|mixed|string
	 */
	public static function oxfordImplode(?array $array, ?string $tag = NULL, ?string $and_or = "and", ?string $glue = ", ")
	{
		# Ensure the array contains at least one value
		if(empty($array)){
			return false;
		}

		# Encapsulate the items in a tag
		if($tag){
			// If the tag is set
			$array = array_map(function($item) use ($tag){
				return "<{$tag}>{$item}</{$tag}>";
			}, $array);
		}

		# If there is only one item, just return it
		if(count($array) == 1){
			return reset($array);
			// This will now have been encapsulated in the tag if it was set
		}

		# Add space to the and/or
		$and_or = " {$and_or} ";

		# If there are only two items, return them with the and/or
		if(count($array) == 2){
			return reset($array) . $and_or . end($array);
		}

		# If there are more than two items, pop the last one off
		$last_element = array_pop($array);

		# Return a glue separated string from the array, add and/or between the last two items
		return implode($glue, $array) . $glue . $and_or . $last_element;
	}

	/**
	 * Strict regex pattern for characters that are not allowed in a filename.
	 */
	const STRICT_PATTERN = /** @lang RegExp */
		'~
        [<>:"/|?*]|              # File system reserved https://en.wikipedia.org/wiki/Filename#Reserved_characters_and_words
        [\x00-\x1F]|             # control characters http://msdn.microsoft.com/en-us/library/windows/desktop/aa365247%28v=vs.85%29.aspx
        [\x7F\xA0\xAD]|          # non-printing characters DEL, NO-BREAK SPACE, SOFT HYPHEN
        [#\[\]@!$&\'()+,;=]|     # URI reserved https://tools.ietf.org/html/rfc3986#section-2.2
        [{}^\~`]                 # URL unsafe characters https://www.ietf.org/rfc/rfc1738.txt
	~x';

	/**
	 * Regex pattern for what characters are not allowed in a Windows OS filename.
	 */
	const WINDOWS_PATTERN = /** @lang RegExp */
		'~
        [<>:"/|?*]|              # File system reserved https://en.wikipedia.org/wiki/Filename#Reserved_characters_and_words
        [\x00-\x1F]|             # control characters http://msdn.microsoft.com/en-us/library/windows/desktop/aa365247%28v=vs.85%29.aspx
        [\x7F\xA0\xAD]|          # non-printing characters DEL, NO-BREAK SPACE, SOFT HYPHEN
        [{}^\~`]                 # URL unsafe characters https://www.ietf.org/rfc/rfc1738.txt
	~x';

	/**
	 * Sanitise a string to be used as a filename.
	 *
	 * @param      $filename
	 * @param bool $beautify
	 *
	 * @return null|string|string[]
	 * @link https://stackoverflow.com/a/42058764/429071
	 */
	public static function filter_filename($filename, ?bool $beautify = false, ?bool $strict = NULL): ?string
	{
		// sanitize filename
		$filename = preg_replace($strict ? self::STRICT_PATTERN : self::WINDOWS_PATTERN, '-', $filename);

		// avoids ".", ".." or ".hiddenFiles"
		$filename = ltrim($filename, '.-');

		// optional beautification
		if($beautify)
			$filename = self::beautify_filename($filename);

		# Get the file extension
		$ext = pathinfo($filename, PATHINFO_EXTENSION);
		$filename_without_extension = pathinfo($filename, PATHINFO_FILENAME);

		# Strip double extensions
		if(substr($filename_without_extension, strlen($ext) * -1) == $ext){
			$filename = $filename_without_extension;
		}

		// maximise filename length to 255 bytes http://serverfault.com/a/9548/44086
		$filename = \mb_strcut(pathinfo($filename, PATHINFO_FILENAME), 0, 255 - ($ext ? strlen($ext) + 1 : 0), mb_detect_encoding($filename)) . ($ext ? '.' . $ext : '');

		return $filename;
	}

	/**
	 * Beautifies a filename.
	 *
	 * @param $filename
	 *
	 * @return string|string[]|null
	 */
	public static function beautify_filename($filename)
	{
		// reduce consecutive characters
		$filename = preg_replace([
			// "file   name.zip" becomes "file-name.zip"
			'/ +/',
			// "file___name.zip" becomes "file-name.zip"
			'/_+/',
			// "file---name.zip" becomes "file-name.zip"
			'/-+/',
		], '-', $filename);

		$filename = preg_replace([
			// "file--.--.-.--name.zip" becomes "file.name.zip"
			'/-*\.-*/',
			// "file...name..zip" becomes "file.name.zip"
			'/\.{2,}/',
		], '.', $filename);

		// ".file-name.-" becomes "file-name"
		$filename = trim($filename, '.-');
		return $filename;
	}

	/**
	 * Checks to see if the remote file exists.
	 * Can also handle local files.
	 *
	 * @param string|null $url
	 *
	 * @return bool
	 * @link https://stackoverflow.com/a/37329149/429071
	 */
	public static function remote_file_exists(?string $url): bool
	{
		# Handle local files
		if(strpos($url, "://") === false){
			return file_exists($url);
		}

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_NOBODY, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); # handles 301/2 redirects
		curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		return $httpCode == 200;
	}

	/**
	 * Given the local path of an SVG file, will convert it to PNG
	 * and return the PNG path.
	 *
	 * @param string   $svg_filename
	 * @param int|null $width Set the width of the output file, default is 1000px
	 *
	 * @return string|null Returns NULL if the file could not be converted.
	 */
	public static function convertSvgToPng(string $svg_filename, ?int $width = 1000): ?string
	{
		$png_filename = "{$svg_filename}.png";

		# Open SVG, convert to PNG, save
		$cmd = "inkscape {$svg_filename} -w{$width} -e {$png_filename}";

		# Run the command
		exec($cmd, $output);

		# Ensure the command was executed successfully
		if(array_pop($output) != "Bitmap saved as: {$png_filename}"){
			return NULL;
		}

		return $png_filename;
	}


	/**
	 * Finds degrees of mismatches between two strings, returns the degree.
	 * If strings are identical, returns 0.
	 * 0. Exact match "a==b"
	 * 1. Match only after cases ignored "lowercase(a)==lowercase(b)"
	 * 2. Match only after unicode diacritics have been removed
	 * 3. Match only after superfluous spaces were removed
	 * 4. Match only after removed excluded words "a==b, except in excluded words"
	 * 5. Match only after excluded non alphanumeric characters
	 * 6. Match only after multi-word strings have been put in the same order "john smith==smith john"
	 * 7. Match except additional words "a contains b, or b contains a, but also has additional strings"
	 * 8. Match except one character (a==b, except 1 character)
	 * 9. Mismatch
	 *
	 * @param      $a
	 * @param      $b
	 * @param null $excluded_words_case_insensitive
	 *
	 * @return int
	 */
	public static function mismatch($a, $b, $excluded_words_case_insensitive = NULL)
	{
		# Format the excluded words (if provided)
		if($excluded_words_case_insensitive){
			if(!is_array($excluded_words_case_insensitive)){
				$string = mb_strtolower($excluded_words_case_insensitive);
				$excluded_words[] = $string;
			}
			else {
				$excluded_words = array_map("mb_strtolower", $excluded_words_case_insensitive);
			}
		}
		else {
			$excluded_words = [];
		}

		# 0. Exact match
		$mismatch = 0;

		if($a == $b){
			return $mismatch;
		}

		# 1. Case mismatch "lowercase(a)==lowercase(b)"
		$mismatch++;

		# Ignore case from here on
		$a = mb_strtolower($a);
		$b = mb_strtolower($b);

		if($a == $b){
			return $mismatch;
		}

		# 2. Ignore unicode mismatches
		$mismatch++;

		# Decompose unicode characters
		$a = preg_replace("/\pM*/u", "", normalizer_normalize($a, \Normalizer::FORM_D));
		$b = preg_replace("/\pM*/u", "", normalizer_normalize($b, \Normalizer::FORM_D));

		if($a == $b){
			return $mismatch;
		}

		# 3. Space mismatches (everything is the same bar the number of spaces)
		$mismatch++;

		# Trim and filter away all superfluous spaces
		$a = implode(" ", array_filter(preg_split('/\s+/', $a)));
		$b = implode(" ", array_filter(preg_split('/\s+/', $b)));

		if($a == $b){
			return $mismatch;
		}

		# 4. excluded words mismatch "lowercase==lowercase, except in excluded words"
		$mismatch++;

		# Ignore the excluded words from here on
		$a = trim(str_replace($excluded_words, "", $a));
		$b = trim(str_replace($excluded_words, "", $b));

		if($a == $b){
			return $mismatch;
		}

		# 5. excluded non alphanumeric characters
		$mismatch++;

		# Trim away everything that isn't A-Z or 0-9, and extra spaces
		$a = implode(" ", array_filter(preg_split('/[^A-Za-z0-9]+/', $a)));
		$b = implode(" ", array_filter(preg_split('/[^A-Za-z0-9]+/', $b)));

		if($a == $b){
			return $mismatch;
		}

		# 6. All the same words, just in a different order
		$mismatch++;

		//@link https://stackoverflow.com/questions/21138505/check-if-two-arrays-have-the-same-values
		//@link https://stackoverflow.com/questions/18720682/sort-a-php-array-returning-new-array
		$a = implode(" ", call_user_func(function(array $a){
			sort($a);
			return $a;
		}, preg_split('/\s+/', $a)));
		$b = implode(" ", call_user_func(function(array $b){
			sort($b);
			return $b;
		}, preg_split('/\s+/', $b)));

		if($a == $b){
			return $mismatch;
		}

		# 7. omitted words mismatch "a contains b, or b contains a, but also has additional strings"
		$mismatch++;

		if(empty(array_diff(explode(" ", $a), explode(" ", $b))) || empty(array_diff(explode(" ", $b), explode(" ", $a)))){
			return $mismatch;
		}

		# 8. spelling mismatch (a==b, except 1 character)
		$mismatch++;

		if(strlen($a) > 3 && strlen($b) > 3){
			//this only applies is both strings are longer than 3 characters
			if((strlen($a) > strlen($b) ? strlen($a) : strlen($b)) - similar_text($a, $b) == 1){
				return $mismatch;
			}
		}

		# 9. If all else fails
		$mismatch++;

		return $mismatch;
	}

	/**
	 * Given a mismatch int, returns a colour name string.
	 *
	 * @param int $mismatch
	 *
	 * @return string
	 */
	public static function mismatch_colour($mismatch)
	{
		switch($mismatch) {
		case 7:
			return "danger";
			break;
		case 6:
			return "warning";
			break;
		case 5:
			return "warning";
			break;
		case 4:
			return "warning";
			break;
		case 3:
			return "info";
			break;
		case 2:
			return "info";
			break;
		case 1:
			return "info";
			break;
		default:
			return "success";
			break;
		}
	}

	/**
	 * Pretty print a binary string as hex.
	 *
	 *
	 * @param string      $data
	 * @param string|null $newline
	 *
	 * @return string
	 * @link https://stackoverflow.com/a/4225813/429071
	 */
	public static function hexDump(string $data, ?string $newline = "\n"): string
	{
		static $from = '';
		static $to = '';

		static $width = 16; # number of bytes per line

		static $pad = '.'; # padding for non-visible characters

		if($from === ''){
			for($i = 0; $i <= 0xFF; $i++){
				$from .= chr($i);
				$to .= ($i >= 0x20 && $i <= 0x7E) ? chr($i) : $pad;
			}
		}

		$hex = str_split(bin2hex($data), $width * 2);
		$chars = str_split(strtr($data, $from, $to), $width);

		$offset = 0;
		foreach($hex as $i => $line){
			$output .= sprintf('%6X', $offset) . ' : ' . implode(' ', str_split($line, 2)) . ' [' . $chars[$i] . ']' . $newline;
			$offset += $width;
		}
		return $output;
	}

	/**
	 * Given a hex (ex. FF0000) value, returns the corresponding
	 * Hue/saturation/lightness values as an array.
	 *
	 * @param string $hex
	 *
	 * @return array
	 */
	static function hex2hsl($hex)
	{
		$hex = [$hex[0] . $hex[1], $hex[2] . $hex[3], $hex[4] . $hex[5]];
		$rgb = array_map(function($part){
			# Ensure the part is a valid hex value
			if(preg_match('/^[0-9a-f]{2}$/i', $part)){
				return hexdec($part) / 255;
			}
			# Otherwise, return NULL to avoid a depreciated error
			return NULL;
		}, $hex);

		$max = max($rgb);
		$min = min($rgb);

		$l = ($max + $min) / 2;

		if($max == $min){
			$h = $s = 0;
		}
		else {
			$diff = $max - $min;
			$s = $l > 0.5 ? $diff / (2 - $max - $min) : $diff / ($max + $min);

			switch($max) {
			case $rgb[0]:
				$h = ($rgb[1] - $rgb[2]) / $diff + ($rgb[1] < $rgb[2] ? 6 : 0);
				break;
			case $rgb[1]:
				$h = ($rgb[2] - $rgb[0]) / $diff + 2;
				break;
			case $rgb[2]:
				$h = ($rgb[0] - $rgb[1]) / $diff + 4;
				break;
			}

			$h /= 6;
		}

		return [$h, $s, $l];
	}

	/**
	 * Given an array of hue/saturation/lightness, returns the corresponding hex value.
	 * The hue value can be either 0-360 or 0-1.
	 * The saturation and lightness values can be either 0-100 or 0-1.
	 * The returned hex value will be in the format #RRGGBB.
	 *
	 * @param array $hsl An array containing hue, saturation, lightness.
	 *
	 * @return string A string containing a HEX colour value (ex. #FF0000 for red)
	 */
	static function hsl2hex(array $hsl): string
	{
		[$h, $s, $l] = $hsl;

		if($h > 1){
			$h /= 360;
		}
		if($s > 1){
			$s /= 100;
		}
		if($l > 1){
			$l /= 100;
		}

		if($s == 0){
			$r = $g = $b = 1;
		}
		else {
			$q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
			$p = 2 * $l - $q;

			$r = str::hue2rgb($p, $q, $h + 1 / 3);
			$g = str::hue2rgb($p, $q, $h);
			$b = str::hue2rgb($p, $q, $h - 1 / 3);
		}

		return "#" . str::rgb2hex($r) . str::rgb2hex($g) . str::rgb2hex($b);
	}

	/**
	 * Given hue, saturation, lightness, returns RGB values.
	 *
	 * @param $p
	 * @param $q
	 * @param $t
	 *
	 * @return float|int
	 */
	static function hue2rgb($p, $q, $t)
	{
		if($t < 0)
			$t += 1;
		if($t > 1)
			$t -= 1;
		if($t < 1 / 6)
			return $p + ($q - $p) * 6 * $t;
		if($t < 1 / 2)
			return $q;
		if($t < 2 / 3)
			return $p + ($q - $p) * (2 / 3 - $t) * 6;

		return $p;
	}

	/**
	 * Given RGB colour, will return corresponding HEX colour value.
	 *
	 * @param $rgb
	 *
	 * @return string
	 */
	static function rgb2hex($rgb)
	{

		# Cass: - Depreciated
		// return str_pad(dechex($rgb * 255), 2, '0', STR_PAD_LEFT);

		# Cass: - Adding an interger sice Dechex request to check Int. 
		return str_pad(dechex((int)($rgb * 255)), 2, '0', STR_PAD_LEFT);


	}

	/**
	 * Is the given colour string a hex colour (or a colour name)?
	 *
	 * @param $colour
	 *
	 * @return bool
	 */
	static function isHexColour($colour)
	{
		if(!is_string($colour)){
			return false;
		}
		if(preg_match('/^#(?:[0-9a-fA-F]{3}){1,2}$/', $colour)){
			return true;
		}
		return false;
	}

	/**
	 * Checks to see if the number string
	 * contains a valid Luhn suffix digit.
	 *
	 * @param $number
	 *
	 * @return bool
	 * @link http://en.wikipedia.org/wiki/Luhn_algorithm
	 *       https://gist.github.com/troelskn/1287893
	 */
	static function isValidLuhn($number): bool
	{
		# Set the type to be a string
		settype($number, 'string');

		# Remove any non-numeric characters
		$number = preg_replace("/[^0-9]/", "", $number);

		# Do the calculations
		$sumTable = [
			[0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
			[0, 2, 4, 6, 8, 1, 3, 5, 7, 9]];
		$sum = 0;
		$flip = 0;
		for($i = strlen($number) - 1; $i >= 0; $i--){
			$sum += $sumTable[$flip++ & 0x1][$number[$i]];
		}

		# Return a boolean result
		return $sum % 10 === 0;
	}

	/**
	 * Takes a colour and prefixes it with "text-",
	 * to get a class string to use as a colour.
	 * The colour does not have to be translated prior.
	 * Alternatively, it will return nothing, if no colour match can be found.
	 *
	 * @param        $colour
	 * @param string $prefix Default "text", can be anything, "btn", etc.
	 *
	 * @return bool|string
	 */
	static function getColour($colour, $prefix = 'text')
	{
		if(!$colour){
			return false;
		}
		if($colour){
			$translated_colour = self::translate_approve_colour($colour);
			return "{$prefix}-{$translated_colour}";
		}
		else {
			return false;
			//The default is no colour (black)
		}
	}

	/**
	 * The approval box colours are limited.
	 *
	 * @param $colour
	 *
	 * @return bool|string
	 */
	static function translate_approve_colour($colour)
	{
		switch($colour) {
		case 'primary'  :
			return 'blue';
		case 'blue'     :
			return 'blue';
		case 'info'     :
			return 'blue';
		case 'green'    :
			return 'green';
		case 'success'  :
			return 'green';
		case 'warning'  :
			return 'orange';
		case 'yellow'   :
			return 'orange';
		case 'orange'   :
			return 'orange';
		case 'danger'   :
			return 'red';
		case 'error'   :
			return 'red';
		case 'red'      :
			return 'red';
		case 'purple'   :
			return 'purple';
		case 'dark'     :
			return 'dark';
		case 'grey'     :
			return 'dark';
		case 'gray'     :
			return 'dark';
		case 'silver'   :
			return 'dark';
		case 'black'    :
			return 'dark';
		default:
			return $colour;
		}
	}

	/**
	 * Generates approval settings, based on the boolean:
	 *
	 * <code>
	 * "approve" => true
	 * </code>
	 * or more complex approval array:
	 * <code>
	 * "approve" => [
	 *    "title" => "Title",
	 *    "message" => "Message",
	 *    "colour" => "Red",
	 *    "icon" => "user"
	 *  ]
	 * </code>
	 *
	 * All the array elements are optional.
	 *
	 * @param             $approve
	 * @param null        $icon
	 * @param string|null $colour
	 *
	 * @return string|null
	 */
	static function getApproveAttr($approve, $icon = NULL, ?string $colour = NULL): ?string
	{
		if(!$approve){
			//If no approve modal is required
			return NULL;
		}

		if(is_array($approve)){
			extract($approve);
		}

		else if(is_bool($approve)){
			$message = "Are you sure you want to do this?";
		}

		else if(is_string($approve)){
			//if just the name of the thing to be removed is given
			$message = str::title("Are you sure you want to {$approve}?");
		}

		# Title
		$title = $title ?: "Are you sure?";

		# Message
		$message = str_replace(["\r\n", "\r", "\n"], " ", $message);

		$icon_class = Icon::getClass($icon);
		$type = self::translate_approve_colour($colour);
		$button_colour = self::getColour($colour, "btn");

		return str::getDataAttr([
			"approve" => [
				"class" => $class,
				"rtl" => $rtl,
				"yes" => $yes,
				"no" => $no,
				"type" => $type,
				"icon" => $icon_class,
				"title" => $title,
				"content" => $message,
				"buttons" => [
					"confirm" => [
						"btnClass" => $button_colour,
					],
				],
			],
		]);
	}

	/**
	 * Shortcut for parent class and style.
	 * <code>
	 * [$parent_class, $parent_style] = str::getClassAndStyle($this->cardHeader, ["col-auto", "card-title"]);
	 * </code>
	 *
	 * @param array      $item
	 * @param array|null $default_parent_class
	 *
	 * @return array
	 */
	public static function getClassAndStyle(array $item, ?array $default_parent_class = NULL): array
	{
		$parent_class_array = str::getAttrArray($item['parent_class'], $default_parent_class, $item['only_parent_class']);
		$parent_class = str::getAttrTag("class", $parent_class_array);
		$parent_style = str::getAttrTag("style", $item['parent_style']);
		return [$parent_class, $parent_style];
	}

	/**
	 * Returns all keys that exist in more than one array child,
	 * but that contain different values across the children.
	 *
	 * @param array $rows
	 *
	 * @return array
	 * @link https://stackoverflow.com/a/58811601/429071
	 */
	public static function getFieldsNonIdentical(array $rows): array
	{
		if(!str::isNumericArray($rows)){
			throw new \InvalidArgumentException("A non numeric array was passed: " . print_r($rows, true));
		}
		if(count($rows) < 2){
			throw new \InvalidArgumentException("You should pass at least 2 rows to compare");
		}

		$compareArr = [];
		$keyDifferentArr = [];
		foreach($rows as $row){
			foreach($row as $key => $val){
				if(!key_exists($key, $compareArr)){
					$compareArr[$key] = $val;
				}
				else if($compareArr[$key] !== $val){
					$keyDifferentArr[$key] = true;
				}
			}
		}
		return array_keys($keyDifferentArr);
	}

	/**
	 * Given a key-value array,
	 * returns a string of `data-key=val`,
	 * to be fed into a tag.
	 * If the array is multi-dimensional (aka. the `val` is an array),
	 * will `json_encode()` the `val`.
	 *
	 * @param array|null $a
	 * @param bool|null  $keep_empty If set to TRUE, will keep the keys with empty vals, otherwise those keys will be
	 *                               removed.
	 *
	 * @return string|bool
	 * @link https://stackoverflow.com/questions/13705473/escaping-quotes-and-html-in-a-data-attribute-json-object
	 * @link https://stackoverflow.com/a/1081581/429071
	 */
	static function getDataAttr(?array $a, ?bool $keep_empty = NULL): ?string
	{
		if(!is_array($a)){
			return NULL;
		}

		foreach($a as $key => $val){
			if(!$keep_empty){
				if($val === NULL || $val === "" || $val === []){
					continue;
				}
			}

			# Treat array and string values differently
			if(is_array($val)){
				//Value is an array, store as single quoted string

				if($key != "dependency"){
					# Remove empty (unless requested otherwise)
					$val = $keep_empty ? $val : str::array_filter_recursive($val, true);
					// Dependency values will be boolean at times
				}

				# JSON encode
				$val = json_encode($val);

				# Escape single quotes
				$val = str_replace("'", "&#39;", $val);

				# Store as a single quoted string
				$str .= " data-{$key}='{$val}'";

			}

			else {
				//Value is a string, store as double-quoted string

				# Escape double quotes
				$val = str_replace("\"", "&quot;", $val);

				# Store as a double-quoted string
				$str .= " data-{$key}=\"{$val}\"";
			}
		}

		return $str;
	}

	/**
	 * Insert a given element into an array at every nth
	 * array key. Will not insert the element at the end
	 * of an array.
	 *
	 * If the element itself is an array, will wrap the
	 * element into a single array key->value to ensure
	 * that the count doesn't get shifted.
	 *
	 * @param array $array
	 * @param int   $every
	 * @param       $element
	 */
	public static function insertElementEveryNthArrayKey(array &$array, int $every, $element): void
	{
		# Ensure that there is always only "one" element inserted
		if(is_array($element)){
			$element = [$element];
		}
		for($i = 0; $i * ($every + 1) + $every < count($array); $i++){
			array_splice($array, $i * ($every + 1) + $every, 0, $element);
		}
	}

	/**
	 * Add "name" and "full_name", and format first and last names.
	 * And cleans up the email address.
	 *
	 * @param $row
	 *
	 * @return bool
	 */
	public static function addNames(&$row): void
	{
		if(!$row){
			return;
		}

		$users = str::isNumericArray($row) ? $row : [$row];
		//If there is only one user, add a numerical key index

		# Go thru each user, even if there is only one
		foreach($users as $id => $user){
			$user['first_name'] = str::title($user['first_name'], true);
			$user['last_name'] = str::title($user['last_name'], true);
			$name = "{$user['first_name']} {$user['last_name']}";
			$user['name'] = $name;
			$user['full_name'] = $name;
			$user['email'] = mb_strtolower($user['email']);
			$users[$id] = $user;
		}

		$row = str::isAssociativeArray($row) ? reset($users) : $users;
		//If there was only one user, skip the numerical key index
	}

	/**
	 * Given address fields, compiles them into the HTML field "address".
	 *
	 * @param $row
	 */
	public static function formatAddress(&$row): void
	{
		if(!$row){
			return;
		}

		$row['address'] = implode("<br>", array_filter([
			$row['address_line_1'],
			$row['address_line_2'],
			$row['address_level_1'],
			implode(" ", [$row['address_level_2'], $row['post_code']]),
			$row['country'][0]['name'],
		]));
	}


	/**
	 * Same as the `array_filter()` function, but works
	 * on multi-dimensional arrays.
	 *
	 * @param           $input
	 * @param bool|null $leave_zeros If set, will only remove values
	 *                               that are === NULL, empty strings,
	 *                               and empty arrays but not "0" values
	 *
	 * @return array
	 */
	static function array_filter_recursive($input, ?bool $leave_zeros = NULL)
	{
		foreach($input as &$value){
			if(is_array($value)){
				$value = str::array_filter_recursive($value, $leave_zeros);
			}
		}

		if($leave_zeros){
			# Filter away NULL, empty strings, and empty arrays
			$filtered_input = array_filter($input, static function($var){
				return $var !== NULL && $var !== "" && $var !== [];
			});

			# If all is left is an empty array, get rid of that too
			if(is_array($filtered_input) && empty($filtered_input)){
				return NULL;
			}

			return $filtered_input;
		}

		return array_filter($input);
	}

	/**
	 * Will return the first value in the array that is not NULL.
	 *
	 * @param array|null $a
	 *
	 * @return mixed|null
	 */
	static function useFirst(?array $a = [])
	{
		foreach($a as $v){
			if($v !== NULL){
				return $v;
			}
		}

		return NULL;
	}

	/**
	 * array_merge_recursive does indeed merge arrays, but it converts values with duplicate
	 * keys to arrays rather than overwriting the value in the first array with the duplicate
	 * value in the second array, as array_merge does. I.e., with array_merge_recursive,
	 * this happens (documented behavior):
	 *
	 * ```
	 * array_merge_recursive(['key' => 'org value'], ['key' => 'new value']);
	 *     => ['key' => ['org value', 'new value']);
	 *```
	 *
	 * array_merge_recursive_distinct does not change the datatypes of the values in the arrays.
	 * Matching keys' values in the second array overwrite those in the first array, as is the
	 * case with array_merge, i.e.:
	 *
	 * ```
	 * array_merge_recursive_distinct(['key' => 'org value'], ['key' => 'new value']);
	 *     => ['key' => 'new value'];
	 * ```
	 *
	 * Parameters are passed by reference, though only for performance reasons. They're not
	 * altered by this function.
	 *
	 * @param array $array1
	 * @param array $array2
	 *
	 * @return array
	 * @author Daniel <daniel (at) danielsmedegaardbuus (dot) dk>
	 * @author Gabriel Sobrinho <gabriel (dot) sobrinho (at) gmail (dot) com>
	 * @link   https://3v4l.org/RP62C
	 */
	public static function array_merge_recursive_distinct(array &$array1, array &$array2)
	{
		$merged = $array1;

		foreach($array2 as $key => &$value){
			if(is_array($value) && isset ($merged [$key]) && is_array($merged [$key])){
				$merged [$key] = str::array_merge_recursive_distinct($merged [$key], $value);
			}
			else {
				$merged [$key] = $value;
			}
		}

		return $merged;
	}

	/**
	 * Simple shortcut to suffix a string with the ellipsis class.
	 *
	 * @param string|null $string
	 * @param string|null $tag
	 *
	 * @return string
	 */
	public static function ellipsis(?string $string, ?string $tag = "span"): string
	{
		return "{$string}<{$tag} class=\"ellipsis\"></{$tag}>";
	}

	/**
	 * Just like the PHP function `trim()`, but removes all kinds of whitespace.
	 *
	 * \p{Z}: Matches any kind of whitespace or invisible separator, including spaces, tabs, and line breaks. This is a
	 * Unicode property that covers a broad range of space characters.
	 * \s: In a Unicode-aware context (like with the u modifier), this matches all whitespace characters, including
	 * space, tab, newline (\n), carriage return (\r), and other Unicode space characters.
	 * \x{2000}-\x{200F}: Zero-Width Space, and a bunch of other esoteric space and non-width characters.
	 * \x{2028}: Line Separator, a character used to denote the end of a line of text.
	 * \x{2029}: Paragraph Separator, a character used to denote the end of a paragraph.
	 * \x{00A0}: Non-Breaking Space, a space character that prevents an automatic line break at its position.
	 * \x{3000}: Ideographic Space, used in CJK (Chinese, Japanese, Korean) typography.
	 * \x{FEFF}: Zero Width No-Break Space, a zero-width space that is not a line-breaking space and prevents
	 * consecutive whitespace characters from collapsing.
	 *
	 * @param string|null $string
	 *
	 * @return string|null
	 * @link https://www.compart.com/en/unicode/block/U+2000
	 *
	 */
	public static function trim(?string $string): ?string
	{
		return preg_replace('/^[\p{Z}\s\x{2000}-\x{200F}\x{2028}\x{2029}\x{00A0}\x{3000}\x{FEFF}]+|[\p{Z}\s\x{2000}-\x{200F}\x{2028}\x{2029}\x{00A0}\x{3000}\x{FEFF}]+$/u', '', $string);
	}

	const WORDS_TO_IGNORE_FOR_INITIALS = [
		"van",
		"von",
		"de",
		"der",
		"la",
		"le",
		"del",
		"di",
		"da",
		"el",
		"los",
		"las",
		"al",
		"il",
		"lo",
	];

	/**
	 * Given a name string, returns the initials.
	 *
	 * @param string|null $name
	 *
	 * @return string|null
	 */
	public static function getInitialsFromNameString(?string $name): ?string
	{
		if(!$name){
			return NULL;
		}

		$words = explode(" ", $name);
		$initials = "";
		foreach($words as $word){
			if(in_array(mb_strtolower($word), self::WORDS_TO_IGNORE_FOR_INITIALS)){
				continue;
			}
			$initials .= mb_substr($word, 0, 1);
		}

		return mb_strtoupper($initials);
	}

	/**
	 * Given a number, returns the number as words.
	 * Used by the `numberAsWords()` function.
	 */
	const NUMERIC_DICTIONARY = [
		0 => 'zero',
		1 => 'one',
		2 => 'two',
		3 => 'three',
		4 => 'four',
		5 => 'five',
		6 => 'six',
		7 => 'seven',
		8 => 'eight',
		9 => 'nine',
		10 => 'ten',
		11 => 'eleven',
		12 => 'twelve',
		13 => 'thirteen',
		14 => 'fourteen',
		15 => 'fifteen',
		16 => 'sixteen',
		17 => 'seventeen',
		18 => 'eighteen',
		19 => 'nineteen',
		20 => 'twenty',
		30 => 'thirty',
		40 => 'forty',
		50 => 'fifty',
		60 => 'sixty',
		70 => 'seventy',
		80 => 'eighty',
		90 => 'ninety',
		100 => 'hundred',
		1000 => 'thousand',
		1000000 => 'million',
		1000000000 => 'billion',
		1000000000000 => 'trillion',
		1000000000000000 => 'quadrillion',
		1000000000000000000 => 'quintillion',
	];

	/**
	 * Given a number, returns the number as words.
	 * Will also handle decimals.
	 * Will return NULL if the number is not numeric.
	 * Will return "zero" if the number is 0.
	 * Will return "negative" if the number is negative.
	 *
	 * @param float|null $number
	 *
	 * @return string|null
	 */
	public static function numberAsWords(?float $number): ?string
	{
		$hyphen = '-';
		$conjunction = ' and ';
		$separator = ', ';
		$negative = 'negative ';
		$decimal = ' point ';

		if(!is_numeric($number)){
			return false;
		}

		if($number < 0){
			return $negative . str::numberAsWords(abs($number));
		}

		$string = $fraction = NULL;

		if(strpos($number, '.') !== false){
			[$number, $fraction] = explode('.', $number);
		}

		switch(true) {
		case $number < 21:
			$string = self::NUMERIC_DICTIONARY[$number];
			break;
		case $number < 100:
			$tens = ((int)($number / 10)) * 10;
			$units = $number % 10;
			$string = self::NUMERIC_DICTIONARY[$tens];
			if($units){
				$string .= $hyphen . self::NUMERIC_DICTIONARY[$units];
			}
			break;
		case $number < 1000:
			$hundreds = $number / 100;
			$remainder = $number % 100;
			$string = self::NUMERIC_DICTIONARY[$hundreds] . ' ' . self::NUMERIC_DICTIONARY[100];
			if($remainder){
				$string .= $conjunction . str::numberAsWords($remainder);
			}
			break;
		default:
			$baseUnit = pow(1000, floor(log($number, 1000)));
			$numBaseUnits = (int)($number / $baseUnit);
			$remainder = $number % $baseUnit;
			$string = str::numberAsWords($numBaseUnits) . ' ' . self::NUMERIC_DICTIONARY[$baseUnit];
			if($remainder){
				$string .= $remainder < 100 ? $conjunction : $separator;
				$string .= str::numberAsWords($remainder);
			}
			break;
		}

		if(NULL !== $fraction && is_numeric($fraction)){
			$string .= $decimal;
			$words = [];
			foreach(str_split((string)$fraction) as $number){
				$words[] = self::NUMERIC_DICTIONARY[$number];
			}
			$string .= implode(' ', $words);
		}

		return $string;
	}
}