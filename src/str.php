<?php

namespace App\Common;

use App\UI\Badge;
use App\UI\Button;
use App\UI\Dropdown;
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
	 * Defines the mininum phone number length requirement.
	 */
	const MINIMUM_PHONE_NUMBER_LENGTH = 5;

	/**
	 * Words or abbreviations that should always be all uppercase
	 */
	const ALL_UPPERCASE = [
		"VAT",
		"CT",
		"YE",
		"PE",
		"BK",
		"MA",
		"SEIS",
		"LLP",
	];

	/**
	 * Words or abbreviations that should always be all lowercase
	 */
	const ALL_LOWERCASE = [
		"a",
		"and",
		"as",
		"by",
		"in",
		"of",
		"or",
		"to",
	];

	/**
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
	static function capitalise_name($str, $is_name = false, $all_words = false)
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
			}, strtolower(trim($str)));
		}
		else if(!$all_words){
			//if only the first word is to be capitalised
			$str_array = explode(" ", $str);
			$first_word = array_shift($str_array);
			$str = ucfirst(strtolower(trim($str)));
			if(is_array(self::ALL_UPPERCASE)){
				$all_uppercase = '';
				foreach(self::ALL_UPPERCASE as $uc){
					if($first_word == strtolower($uc)){
						$str = strtoupper($first_word) . " " . implode(" ", $str_array);
					}
					$all_uppercase .= strtolower($uc) . '|';
					//set them all to lowercase
				}
			}
			if(is_array(self::ALL_LOWERCASE)){
				$all_lowercase = '';
				foreach(self::ALL_LOWERCASE as $uc){
					if($first_word == strtolower($uc)){
						$str = strtolower($first_word) . " " . implode(" ", $str_array);
					}
					$all_lowercase .= strtolower($uc) . '|';
					//set them all to lowercase
				}
			}
		}
		else {
			// addresses, essay titles ... and anything else
			if(is_array(self::ALL_UPPERCASE)){
				foreach(self::ALL_UPPERCASE as $uc){
					$all_uppercase .= ucfirst(strtolower($uc)) . '|';
				}
			}
			if(is_array(self::ALL_LOWERCASE)){
				foreach(self::ALL_LOWERCASE as $uc){
					$all_lowercase .= ucfirst($uc) . '|';
				}
			}
			// captialize all first letters
			//$str = preg_replace('/\\b(\\w)/e', 'strtoupper("$1")', strtolower(trim($str)));
			$str = preg_replace_callback("/\\b(\\w)/u", function($matches){
				return strtoupper($matches[1]);
			}, strtolower(trim($str)));
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
				//$str = preg_replace("/\\b($all_lowercase)\\b/e", 'strtolower("$1")', $str);
				$str = preg_replace_callback("/\\b($all_lowercase)\\b/u", function($matches){
					return strtolower($matches[1]);
				}, $str);
			}
			else {
				// first and last word will not be changed to lower case (i.e. titles)
				//$str = preg_replace("/(?<=\\W)($all_lowercase)(?=\\W)/e", 'strtolower("$1")', $str);
				$str = preg_replace_callback("/(?<=\\W)($all_lowercase)(?=\\W)/u", function($matches){
					return strtolower($matches[1]);
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
			//$str = preg_replace("/(\\w)($suffixes)\\b/e", '"$1".strtolower("$2")', $str);
			$str = preg_replace_callback("/(\\w)($suffixes)\\b/u", function($matches){
				return "${matches[1]}" . strtolower($matches[2]);
			}, $str);
		}
		return $str;
	}

	/**
	 * Fixes the casing on all titles
	 * <code>
	 * str::title("str");
	 * str::title("name",true);
	 * str::title("not only first word",false,true);
	 * </code>
	 *
	 * @param      $string
	 * @param bool $is_name
	 * @param bool $all_words
	 *
	 * @return mixed
	 */
	static function title($string, $is_name = false, $all_words = false)
	{
		$string = str_replace("doc_", "document_", $string);
		return self::capitalise_name(str_replace("_", " ", $string), $is_name, $all_words);
	}

	/**
	 * Given a title string, will suffix with (n), where in is an incremental
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
	public static function flattenSingleChildren(array &$array, array $keys): void
	{
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
	public static function base64_decode_url($string)
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

		foreach ($elements as $element) {
			if ($element[$parent_id_col_name] == $parentId) {
				$children = self::buildTree($elements, $id_col_name, $parent_id_col_name, $element[$id_col_name]);
				if ($children) {
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
	static function backtrace($return = NULL)
	{
		$steps = [];
		array_walk(debug_backtrace(), function($a) use (&$steps){
			$steps[] = "{$a['function']}(" . json_encode($a['args']) . ");\r\n[" . str_pad($a['line'], 4, " ", STR_PAD_LEFT) . "] " . basename($a['file']) . "->";
		});

		# Fix is so that the functions and filenames are aligned correctly
		$steps_string = implode("", $steps);
		$correctly_aligned_steps = array_reverse(explode("\r\n", $steps_string));

		# Remove the first step, which is always the string ajax.php
		array_shift($correctly_aligned_steps);
		# Remove the last step, which is just the string "backtrace()"
		array_pop($correctly_aligned_steps);

		if($return){
			return implode("\r\n", $correctly_aligned_steps);
		}

		print implode("\r\n", $correctly_aligned_steps);
		exit;
	}

	/**
	 * Checks to see if this is the DEV environment.
	 *
	 * @return bool
	 */
	public static function isDev(): bool
	{
		return $_SERVER['SERVER_ADDR'] === $_ENV['dev_ip'];
		// === because when accessed from the CLI, SERVER_ADDR = NULL, and if the dev_ip is NOT set (""), will result is a false positive match
	}

	/**
	 * Returns TRUE if script is run from the command line (CLI),
	 * or false if it's run from a browser (http).
	 * @return bool
	 */
	static function runFromCLI(): bool
	{
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
	 * For *external* files (only?)
	 *
	 * @param string $filePath
	 *
	 * @return bool
	 * @link https://stackoverflow.com/a/10494842/429071
	 */
	public static function isSvg(string $filePath)
	{
		return in_array("Content-Type: image/svg+xml", get_headers($filePath));
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
			 * Strip HTML and PHP tags from the string.
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
		if(!is_string($str)){
			return false;
		}
		$json = json_decode($str);
		return $json && $str != $json;
	}

	/**
	 * Validates a phone number.
	 * TODO Expand to include fixing/adding country codes etc.
	 *
	 * @param $number
	 *
	 * @return bool
	 */
	public static function isValidPhoneNumber($number)
	{
		$number = preg_replace("/[^0-9+]/", "", $number);
		if(strlen($number) < self::MINIMUM_PHONE_NUMBER_LENGTH){
			return false;
		}
		return true;
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
	public static function getClassesFromPath(string $path): array
	{
		$finder = new \Symfony\Component\Finder\Finder;
		$iter = new \hanneskod\classtools\Iterator\ClassIterator($finder->in($path));
		return array_keys($iter->getClassMap());
	}

	/**
	 * Returns an MD5 of an array or string.
	 *
	 * Case INsensitive.
	 *
	 * @param array|string $value
	 *
	 * @return string
	 */
	public static function getHash($value): string
	{
		return md5(strtolower(json_encode($value)));
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
		$cmd = "go(function(){";
		$cmd .= "require \"/var/www/html/app/settings.php\";";
		$cmd .= "\$class = new \ReflectionClass(\"" . str_replace("\\", "\\\\", $class) . "\");";
		$cmd .= "echo json_encode(\$class->getMethods(\ReflectionMethod::IS_{$modifier}));";
		$cmd .= "});";

		# Run the command
		$json_output = shell_exec("php -r '{$cmd}' 2>&1");

		# Return false if no methods matching are found
		if(!$array = json_decode($json_output, true)){
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
	public static function getMethodCase($snake)
	{
		return lcfirst(str_replace("_", "", ucwords($snake, "_")));
	}

	/**
	 * Given a rel_table, and an optional parent_class, find a class
	 *
	 * @param string      $rel_table    The class you're looking for
	 * @param string|null $parent_class The parent class if it's different from the class itself
	 *
	 * @return bool|string Returns the class with path or FALSE if it can't find it
	 */
	public static function findClass(string $rel_table, ?string $parent_class = NULL)
	{
		# An optional parent class can be supplied
		$parent_class = $parent_class ?: $rel_table;

		# Does an App path exist?
		$corePath = str::getClassCase("\\App\\{$parent_class}\\{$rel_table}");
		if(class_exists($corePath)){
			return $corePath;
		}

		# Does a Common path exist?
		$commonPath = str::getClassCase("\\App\\Common\\{$parent_class}\\{$rel_table}");
		if(class_exists($commonPath)){
			return $commonPath;
		}

		# Does an API path exist?
		$corePath = str::getClassCase("\\API\\{$parent_class}\\{$rel_table}");
		if(class_exists($corePath)){
			return $corePath;
		}

		# If no class can be found
		return false;
	}

	/**
	 * Converts snake_case to ClassCase (CamelCase).
	 * Will also convert \App\common\rel_table to \App\Common\RelTable
	 *
	 * @param $snake
	 *
	 * @return string|string[]
	 * @return string|string[]
	 */
	public static function getClassCase($snake)
	{
		return str_replace("_", "", ucwords($snake, "_\\"));
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
	public static function camelToSnakeCase(string $string, string $us = "_"): string
	{
		return str_replace(" ", $us, strtolower(preg_replace(
			'/(?<=\d)(?=[A-Za-z])|(?<=[A-Za-z])(?=\d)|(?<=[a-z])(?=[A-Z])/', $us, $string)));
	}

	/**
	 * Returns an array of the following:
	 * <code>
	 * {
	 *   "ip": "134.201.250.155",
	 *   "hostname": "134.201.250.155",
	 *   "type": "ipv4",
	 *   "continent_code": "NA",
	 *   "continent_name": "North America",
	 *   "country_code": "US",
	 *   "country_name": "United States",
	 *   "region_code": "CA",
	 *   "region_name": "California",
	 *   "city": "Los Angeles",
	 *   "zip": "90013",
	 *   "latitude": 34.0453,
	 *   "longitude": -118.2413,
	 *   "location": {
	 *     "geoname_id": 5368361,
	 *     "capital": "Washington D.C.",
	 *     "languages": [
	 *         {
	 *           "code": "en",
	 *           "name": "English",
	 *           "native": "English"
	 *         }
	 *     ],
	 *     "country_flag": "https://assets.ipstack.com/images/assets/flags_svg/us.svg",
	 *     "country_flag_emoji": "🇺🇸",
	 *     "country_flag_emoji_unicode": "U+1F1FA U+1F1F8",
	 *     "calling_code": "1",
	 *     "is_eu": false
	 *   },
	 *   "time_zone": {
	 *     "id": "America/Los_Angeles",
	 *     "current_time": "2018-03-29T07:35:08-07:00",
	 *     "gmt_offset": -25200,
	 *     "code": "PDT",
	 *     "is_daylight_saving": true
	 *   },
	 *   "currency": {
	 *     "code": "USD",
	 *     "name": "US Dollar",
	 *     "plural": "US dollars",
	 *     "symbol": "$",
	 *     "symbol_native": "$"
	 *   },
	 *   "connection": {
	 *     "asn": 25876,
	 *     "isp": "Los Angeles Department of Water & Power"
	 *   },
	 *   "security": {
	 *     "is_proxy": false,
	 *     "proxy_type": null,
	 *     "is_crawler": false,
	 *     "crawler_name": null,
	 *     "crawler_type": null,
	 *     "is_tor": false,
	 *     "threat_level": "low",
	 *     "threat_types": null
	 *   }
	 * }
	 * </code>
	 *
	 * @param null $ip
	 *
	 * @return bool|mixed
	 */
	public static function ip($ip = NULL)
	{
		if(!$_ENV['ipstack_access_key']){
			//if a key hasn't been set, ignore this
			return false;
		}
		$ip = $ip ?: $_SERVER['REMOTE_ADDR'];
		//if an IP isn't explicitly given, use the requester's IP

		$client = new \GuzzleHttp\Client([
			"base_uri" => "http://api.ipstack.com/",
		]);

		try {
			$response = $client->request("GET", $ip, [
				"query" => [
					"access_key" => $_ENV['ipstack_access_key'],
				],
			]);
		}
		catch(\Exception $e) {
			//Catch errors
			return false;
		}

		return json_decode($response->getBody()->getContents(), true);
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
	 * Crucually, the string is not prefixed with a slash.
	 *
	 * @param array $array
	 * @param bool  $urlencoded If set to yes, will urlencode the hash string
	 *
	 * @return string
	 */
	static function generate_uri($array, $urlencoded = NULL)
	{

		# If an array is given (most common)
		if(is_array($array)){
			extract($array);
			$hash = "{$rel_table}/{$rel_id}/{$action}";
			if(!is_array($vars)){
				//if there are no variables attached

				# Remove any surplus slashes at the end
				$hash = rtrim($hash, "/");
			}
		}

		# If a string is given (less common)
		if(is_string($array)){
			$hash = $array;
		}

		# If there are vars (in the array)
		if(is_array($vars)){
			# Callbacks can be their own URI in array form, make sure they're converted to string
			if(is_array($vars['callback'])){
				$vars['callback'] = str::generate_uri($vars['callback'], true);
			}
			if(count($vars) == count($vars, COUNT_RECURSIVE)){
				//if the vars array is NOT multi-dimensional
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
	 * @param $str
	 *
	 * @return string
	 */
	static function urlencode($str)
	{
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
	 * @return array|bool
	 */
	static function explode($delimiters, $string)
	{
		if(!is_array(($delimiters)) && !is_array($string)){
			//if neither the delimiter nor the string are arrays
			return explode($delimiters, $string);
		}
		else if(!is_array($delimiters) && is_array($string)){
			//if the delimiter is not an array but the string is
			foreach($string as $item){
				foreach(explode($delimiters, $item) as $sub_item){
					$items[] = $sub_item;
				}
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
		return false;
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
	 * Given a nubmer, assumed to be bytes,
	 * returns the corresponding value in B-TB,
	 * with suffix.
	 *
	 * @param int $bytes     A number of bytes
	 * @param int $precision How precise to represent the number
	 *
	 * @return string
	 */
	static function bytes($bytes, $precision = 2)
	{
		$units = ['B', 'KB', 'MB', 'GB', 'TB'];

		$bytes = max($bytes, 0);
		$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
		$pow = min($pow, count($units) - 1);
		$bytes /= pow(1024, $pow);

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

		if(substr(trim(strtolower($script)), 0, strlen("<script>")) == "<script>"){
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
					else if (!is_array($k) && is_array($v)){
						$val .= "{$k}:".end($v).";";
					}
					else {
						if($k && strlen($v)){
							$val .= "{$k}:{$v};";
						}
					}
				}
			}
		}

		if(!strlen(trim($val))){
			//if there is no visible val
			if($if_null){
				//if there is a replacement
				$val = $if_null;
			}
			else {
				//otherwise peace out
				return false;
			}
		}

		$val = str_replace("\"", "&quot;", $val);
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
	 * @param string $string String is case insensitive.
	 *
	 * @return bool Returns TRUE if the pattern matches given subject,
	 *                FALSE if it does not or if an error occurred.
	 */
	public static function isUuid(?string $string): bool
	{
		return preg_match("/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i", $string);
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
	 * Given an mySQL datetime value, will return a HTML string with JavaScript
	 * that will display the amount of time *ago* that timestap was.
	 * The amount of time will change as time passes.
	 *
	 * @param datetime $datetime_or_time mySQL datetime value
	 * @param bool     $future           If set to to true will also show future times
	 *
	 * @return string
	 * @throws \Exception
	 */
	static function ago($datetime_or_time, $future = NULL)
	{
		if(!$datetime_or_time){
			return '';
		}
		else if(is_object($datetime_or_time)){
			$then = clone $datetime_or_time;
			$datetime_or_time = $then->format("Y-m-d H:i:s");
		}
		else {
			$then = new \DateTime($datetime_or_time);
		}

		if($future){
			$future = "jQuery.timeago.settings.allowFuture = true;";
		}

		$id = str::id("timeago");
		//gives the timeago element uniqueness, in case it is used as the key in an array
		return <<<EOF
<time id="{$id}" class="timeago" datetime="{$then->format('c')}">{$datetime_or_time}</time> [{$then->format('j M Y')}]
<script>{$future}$("time.timeago").timeago();</script>
EOF;
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
	 *
	 * @return string|null
	 */
	static function number(?float $amount, ?string $currency = NULL, ?int $padding = NULL, ?int $decimals = 2): ?string
	{
		# A number (even if it's "0") is required
		if(!strlen($amount)){
			return NULL;
		}

		# include thousand separators, decimals and a decimal point
		$amount = number_format($amount, $decimals, '.', ',');

		# Pad if required
		if($padding){
			$amount = str_pad($amount, $padding, " ", STR_PAD_LEFT);
		}

		# Prefix with currency symbol + space
		if($currency){
			$amount = "{$currency} {$amount}";
		}

		return str::monospace($amount);
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
		$text = str_replace(" ", "&nbsp;", $text);

		# ID (optional)
		$id = str::getAttrTag("id", $id);

		# Class
		$class_array = str::getAttrArray($class, ["text-monospace"], $only_class);
		$class = str::getAttrTag("class", $class_array);

		# Style
		$style = str::getAttrTag("style", $style);

		# Alt
		$alt = str::getAttrTag("title", $alt);

		return "<span{$id}{$class}{$style}{$alt}>{$text}</span>";
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
	 * Given a date string, returns a datetime object,
	 * where the time is set to 00:00:00.
	 *
	 * @param string $date
	 *
	 * @return \DateTime
	 * @throws \Exception
	 */
	public static function newDateTimeDateOnly(?string $date = NULL): \DateTime
	{
		$dt = new \DateTime($date);
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
	 *
	 * @param string|array $str
	 * @param bool         $crop     If set to TRUE, will crop the output length.
	 * @param null         $language If a language is given, will become formatted with PrismJS
	 *
	 * @return string
	 */
	public static function pre($str, $crop = NULL, $language = NULL)
	{
		if(is_array($str)){
			$str = str::var_export($str, true);
		}

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

		if($language){
			$class = str::getAttrTag("class", "language-{$language}");
			$str = str_replace("<code>", "<code{$class}>", $str);
		}

		$str = "<div class=\"code-mute\">{$str}</div>";
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
	 * <code>
	 * $order = [
	 *    "countryCode" => "asc",
	 *    "stateProv" => "asc",
	 *    "city" => "asc",
	 *    "addr1" => "asc",
	 * ];
	 * </code>
	 * @param array $array
	 * @param array $order
	 *
	 * @link https://stackoverflow.com/a/9261304/429071
	 */
	static function multidimensionalOrderBy(array &$array, array $order): void
	{
		uasort($array, function($a, $b) use ($order){
			$t = [true => -1, false => 1];
			$r = true;
			$k = 1;
			foreach($order as $key => $value){
				$k = (strtolower($value) === 'asc') ? 1 : -1;
				$r = ($a[$key] < $b[$key]);
				if($a[$key] !== $b[$key]){
					return $t[$r] * $k;
				}

			}
			return $t[$r] * $k;
		});
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
	 * AU for when you want to capitalise the first letter (only)
	 * because it's placed in the beginning of a sentence.
	 *
	 * @param string $input
	 * @param int    $count
	 *
	 * @return string
	 */
	public static function AU($input, $count = 1)
	{
		return ucfirst(self::A($input, $count));
	}

	/**
	 * Mirror for self::A
	 *
	 * @param     $input
	 * @param int $count
	 *
	 * @return string
	 */
	public static function AN($input, $count = 1)
	{
		return self::A($input, $count);
	}

	/**
	 * @param     $input
	 * @param int $count
	 *
	 * @return string
	 */
	public static function A($input, $count = 1)
	{
		$matches = [];
		$matchCount = preg_match("/\A(\s*)(?:an?\s+)?(.+?)(\s*)\Z/i", $input, $matches);
		[$all, $pre, $word, $post] = $matches;
		if(!$word)
			return $input;
		$result = self::_indef_article($word, $count);
		return $pre . $result . $post;
	}

	# THIS PATTERN MATCHES STRINGS OF CAPITALS STARTING WITH A "VOWEL-SOUND"
	# CONSONANT FOLLOWED BY ANOTHER CONSONANT, AND WHICH ARE NOT LIKELY
	# TO BE REAL WORDS (OH, ALL RIGHT THEN, IT'S JUST MAGIC!)

	private static $A_abbrev = "(?! FJO | [HLMNS]Y.  | RY[EO] | SQU
		  | ( F[LR]? | [HL] | MN? | N | RH? | S[CHKLMNPTVW]? | X(YL)?) [AEIOU])
			[FHLMNRSX][A-Z]
		";

	# THIS PATTERN CODES THE BEGINNINGS OF ALL ENGLISH WORDS BEGINING WITH A
	# 'y' FOLLOWED BY A CONSONANT. ANY OTHER Y-CONSONANT PREFIX THEREFORE
	# IMPLIES AN ABBREVIATION.

	private static $A_y_cons = 'y(b[lor]|cl[ea]|fere|gg|p[ios]|rou|tt)';

	# EXCEPTIONS TO EXCEPTIONS

	//private static $A_explicit_an = "euler|hour(?!i)|heir|honest|hono";
	private static $A_explicit_an = "euler|hour(?!i)|heir|honest|hono|mot";

	private static $A_ordinal_an = "[aefhilmnorsx]-?th";

	private static $A_ordinal_a = "[bcdgjkpqtuvwyz]-?th";

	/**
	 * @param $word
	 * @param $count
	 *
	 * @return string
	 */
	private static function _indef_article($word, $count)
	{
		if($count != 1) // TO DO: Check against $PL_count_one instead
			return "$count $word";

		# HANDLE USER-DEFINED VARIANTS
		// TO DO

		# HANDLE NUMBERS IN DIGIT FORM (1,2 …)
		#These need to be checked early due to the methods used in some cases below

		#any number starting with an '8' uses 'an'
		if(preg_match("/^[8](\d+)?/", $word))
			return "an $word";

		#numbers starting with a '1' are trickier, only use 'an'
		#if there are 3, 6, 9, … digits after the 11 or 18

		#check if word starts with 11 or 18
		if(preg_match("/^[1][1](\d+)?/", $word) || (preg_match("/^[1][8](\d+)?/", $word))){

			#first strip off any decimals and remove spaces or commas
			#then if the number of digits modulus 3 is 2 we have a match
			if(strlen(preg_replace(["/\s/", "/,/", "/\.(\d+)?/"], '', $word)) % 3 == 2)
				return "an $word";
		}

		# HANDLE ORDINAL FORMS
		if(preg_match("/^(" . self::$A_ordinal_a . ")/i", $word))
			return "a $word";
		if(preg_match("/^(" . self::$A_ordinal_an . ")/i", $word))
			return "an $word";

		# HANDLE SPECIAL CASES

		if(preg_match("/^(" . self::$A_explicit_an . ")/i", $word))
			return "an $word";
		if(preg_match("/^[aefhilmnorsx]$/i", $word))
			return "an $word";
		if(preg_match("/^[bcdgjkpqtuvwyz]$/i", $word))
			return "a $word";

		# HANDLE ABBREVIATIONS

		if(preg_match("/^(" . self::$A_abbrev . ")/x", $word))
			return "an $word";
		if(preg_match("/^[aefhilmnorsx][.-]/i", $word))
			return "an $word";
		if(preg_match("/^[a-z][.-]/i", $word))
			return "a $word";

		# HANDLE CONSONANTS

		#KJBJM - the way this is written it will match any digit as well as non vowels
		#But is necessary for later matching of some special cases.  Need to move digit
		#recognition above this.
		#rule is: case insensitive match any string that starts with a letter not in [aeiouy]
		if(preg_match("/^[^aeiouy]/i", $word))
			return "a $word";

		# HANDLE SPECIAL VOWEL-FORMS

		if(preg_match("/^e[uw]/i", $word))
			return "a $word";
		if(preg_match("/^onc?e\b/i", $word))
			return "a $word";
		if(preg_match("/^uni([^nmd]|mo)/i", $word))
			return "a $word";
		if(preg_match("/^ut[th]/i", $word))
			return "an $word";
		if(preg_match("/^u[bcfhjkqrst][aeiou]/i", $word))
			return "a $word";

		# HANDLE SPECIAL CAPITALS

		if(preg_match("/^U[NK][AIEO]?/", $word))
			return "a $word";

		# HANDLE VOWELS

		if(preg_match("/^[aeiou]/i", $word))
			return "an $word";

		# HANDLE y... (BEFORE CERTAIN CONSONANTS IMPLIES (UNNATURALIZED) "i.." SOUND)

		if(preg_match("/^(" . self::$A_y_cons . ")/i", $word))
			return "an $word";

		#DEFAULT CONDITION BELOW
		# OTHERWISE, GUESS "a"
		return "a $word";
	}

	/**
	 * Flatten multidimentional array.
	 *
	 * @param array  $array The array to flatten
	 * @param string $glue  The glue to use in the flattened array keys. Default is dot-notation.
	 *
	 * @return array|bool
	 * @link https://stackoverflow.com/a/10424516/429071
	 */
	public static function flatten($array, $glue = '.')
	{
		if(!is_array($array)){
			return false;
		}
		$ritit = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($array));
		$result = [];
		foreach($ritit as $leafValue){
			$keys = [];
			foreach(range(0, $ritit->getDepth()) as $depth){
				$keys[] = $ritit->getSubIterator($depth)->key();
			}
			$result[join($glue, $keys)] = $leafValue;
		}
		return $result;
	}

	/**
	 * Truncates a given string if it's longer than the max length.
	 * If the last characters are spaces, will trim the spaces.
	 * Will also add a suffix of your chosing. The default is an elipsis.
	 *
	 * @param string      $string
	 * @param int         $max_length
	 * @param string|null $suffix
	 *
	 * @return string|null
	 */
	public static function truncateIf(?string $string, int $max_length, ?string $suffix = "..."): ?string
	{
		if(!$string){
			return $string;
		}

		if(strlen($string) <= $max_length){
			return $string;
		}

		return trim(substr($string, 0, $max_length)) . $suffix;
	}

	/**
	 * Searches thru a multi-dimentional array for a key,
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
			return $string;
		}

		# If it's missing, return NULL
		if(!strlen($string)){
			return NULL;
		}

		return explode($delimiter, $string);
	}

	/**
	 * Does the leg work deciding "[1 ]thing was ", or "[2 ]things were ".
	 *
	 * @param array|int $array         An array of things that will be counted, or an int of the count.
	 * @param string    $rel_table     The name of the thing that is to be counted.
	 * @param bool      $include_count If set to true, will include the count also.
	 *
	 * @return bool|mixed Returns a string if the vars have been entered correctly, otherwise FALSE.
	 */
	public static function were($array, $rel_table, $include_count = false)
	{
		if(is_array($array)){
			$count = count($array);
		}
		else if(is_int($array) || is_string($array)){
			$count = (int)$array;
		}
		else {
			$count = 0;
		}
		if(!is_string($rel_table)){
			return false;
		}

		if($include_count){
			switch($count) {
			case 0:
				return str::title("No " . str::pluralise($rel_table) . " were");
				break;
			case 1:
				return str::title("1 {$rel_table} was");
				break;
			default:
				return str::title("{$count} " . str::pluralise($rel_table) . " were");
				break;
			}
		}

		switch($count) {
		case 0:
			return str::pluralise($rel_table) . " were";
			break;
		case 1:
			return "{$rel_table} was";
			break;
		default:
			return str::pluralise($rel_table) . " were";
			break;
		}
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
		if(in_array(strtolower($string), self::$uncountable))
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
		if(in_array(strtolower($string), self::$uncountable))
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
	 * Returns an IS or ARE depending whether there is 1 or
	 * any other number (including zero) of something.
	 *
	 * @param mixed     $count        Count can be either a number, or an array (in which case it gets counted)
	 * @param bool|null $passed_tense If set, replaces is/are with was/were
	 *
	 * @return string
	 */
	public static function isAre($count, ?bool $passed_tense = NULL): string
	{
		$count = is_array($count) ? count($count) : $count;
		if($passed_tense){
			return abs($count) == 1 ? "was" : "were";
		}
		return abs($count) == 1 ? "is" : "are";
	}

	/**
	 * Given an array, returns an oxford comma separated,
	 * grammatically correct list of items:
	 * <code>
	 * ["apples","oranges","bananas","pears"]
	 * </code>
	 * //Returns "apples, oranges, bananas and pears"
	 *
	 * @param             $array
	 * @param string|null $glue
	 * @param string|null $and_or
	 * @param string|null $tag
	 *
	 * @return bool|mixed|string
	 */
	public static function oxfordImplode(?array $array, ?string $glue = ", ", ?string $and_or = "and", ?string $tag = NULL)
	{
		if(empty($array)){
			return false;
		}

		if($tag){
			$array = array_map(function($item) use ($tag){
				return "<{$tag}>{$item}</{$tag}>";
			}, $array);
		}

		if(count($array) == 1){
			return reset($array);
		}

		$and_or = " {$and_or} ";

		if(count($array) == 2){
			return reset($array) . $and_or . end($array);
		}

		$last_element = array_pop($array);

		return implode($glue, $array) . $and_or . $last_element;
	}

	/**
	 * Sanitise a string to be used as a filename.
	 *
	 * @param      $filename
	 * @param bool $beautify
	 *
	 * @return null|string|string[]
	 * @link https://stackoverflow.com/a/42058764/429071
	 */
	public static function filter_filename($filename, $beautify = true)
	{
		// sanitize filename
		$filename = preg_replace(
			'~
        [<>:"/|?*]|            # file system reserved https://en.wikipedia.org/wiki/Filename#Reserved_characters_and_words
        [\x00-\x1F]|             # control characters http://msdn.microsoft.com/en-us/library/windows/desktop/aa365247%28v=vs.85%29.aspx
        [\x7F\xA0\xAD]|          # non-printing characters DEL, NO-BREAK SPACE, SOFT HYPHEN
        [#\[\]@!$&\'()+,;=]|     # URI reserved https://tools.ietf.org/html/rfc3986#section-2.2
        [{}^\~`]                 # URL unsafe characters https://www.ietf.org/rfc/rfc1738.txt
        ~x',
			'-', $filename);

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
			$fielname = $filename_without_extension;
		}

		// maximise filename length to 255 bytes http://serverfault.com/a/9548/44086
		$filename = mb_strcut(pathinfo($filename, PATHINFO_FILENAME), 0, 255 - ($ext ? strlen($ext) + 1 : 0), mb_detect_encoding($filename)) . ($ext ? '.' . $ext : '');

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
		// lowercase for windows/unix interoperability http://support.microsoft.com/kb/100625
		$filename = mb_strtolower($filename, mb_detect_encoding($filename));
		// ".file-name.-" becomes "file-name"
		$filename = trim($filename, '.-');
		return $filename;
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
			return hexdec($part) / 255;
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
	 *
	 * @param array $hsl An array containging hue, saturation, lightness.
	 *
	 * @return string A string containgin a HEX colour value (ex. FF0000 for red)
	 */
	static function hsl2hex($hsl)
	{
		[$h, $s, $l] = $hsl;

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

		return str::rgb2hex($r) . str::rgb2hex($g) . str::rgb2hex($b);
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
		return str_pad(dechex($rgb * 255), 2, '0', STR_PAD_LEFT);
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
	 * Get the style of button
	 *
	 * @param $a
	 *
	 * @return mixed
	 */
	static function get_style($a)
	{
		extract($a);

		if($style){
			return "style=\"{$style}\"";
		}

		return false;
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
			break;
		case 'blue'     :
			return 'blue';
			break;
		case 'info'     :
			return 'blue';
			break;
		case 'green'    :
			return 'green';
			break;
		case 'success'  :
			return 'green';
			break;
		case 'warning'  :
			return 'orange';
			break;
		case 'yellow'   :
			return 'orange';
			break;
		case 'orange'   :
			return 'orange';
			break;
		case 'danger'   :
			return 'red';
			break;
		case 'red'      :
			return 'red';
			break;
		case 'purple'   :
			return 'purple ';
			break;
		case 'dark'     :
			return 'dark';
			break;
		case 'grey'     :
			return 'dark';
			break;
		case 'gray'     :
			return 'dark';
			break;
		case 'silver'   :
			return 'dark';
			break;
		case 'black'    :
			return 'dark';
			break;
		default:
			return $colour;
			break;
		}
	}

	/**
	 * Generates approval settings, based on the boolean:
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
	 * All of the array elements are optional.
	 *
	 * @param array|bool|string $approve
	 *
	 * @return bool|string
	 */
	static function getApproveAttr($approve)
	{
		if(!$approve){
			//If no approve modal is required
			return false;
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
			return false;
		}

		foreach($a as $key => $val){
			if(!$keep_empty){
				if(!$val){
					continue;
				}
			}

			# Treat array and string values differently
			if(is_array($val)){
				//Value is an array, store as single quoted string

				# Remove empty (unless requested otherwise)
				$val = $keep_empty ? $val : str::array_filter_recursive($val);

				# JSON encode
				$val = json_encode($val);

				# Escape single quotes
				$val = str_replace("'", "&#39;", $val);

				# Store as a single quoted string
				$str .= " data-{$key}='{$val}'";

			}
			else {
				//Value is a string, store as double quoted string

				# Escape double quotes
				$val = str_replace("\"", "&quot;", $val);

				# Store as a double quoted string
				$str .= " data-{$key}=\"{$val}\"";
			}
		}

		return $str;
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
			$user['email'] = strtolower($user['email']);
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
	 *                               that are === NULL and empty arrays
	 *                               but not "0" values
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
			# Filter === NULLs (only)
			$filtered_input = array_filter($input, static function($var){
				return $var !== NULL;
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
}