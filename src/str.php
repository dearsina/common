<?php

namespace App\Common;

/**
 * Static class related mainly to string manipulation.
 *
 * <code>
 * str::
 * </code>
 * or
 *
 * <code>
 * $this->str->
 * </code>
 *
 * @package App\Common
 */
class str {
	/**
	 * The below code is just here so that str can be used in HEREDOCs by writing
	 * $this->str->method() instead of str::method(), which wont work.
	 *
	 * Can now be pulled in locally so that $this->str->method() can be used.
	 * @link https://stackoverflow.com/questions/8773236/proper-way-to-access-a-static-variable-inside-a-string-with-a-heredoc-syntax/
	 *
	 *
	 * @var bool
	 */
	private $str;
	private static $instance = null;

	private function __construct() {
		$this->str = false;
	}

	private function __clone() {
		// Stopping Clonning of Object
	}

	private function __wakeup() {
		// Stopping unserialize of object
	}

	public static function getInstance() {

		// Check if instance is already exists
		if(self::$instance == null) {
			self::$instance = new str();
		}

		return self::$instance;
	}

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
		"LLP"
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
	 *
	 * You may need to adapt the lists in "$all_uppercase" and "$all_lowercase" to suit the data that you are working with.
	 *
	 * @param $str
	 * @param bool $is_name
	 * @return mixed
	 * @link http://www.php.net/manual/en/function.ucwords.php#60064
	 */
	static function capitalise_name($str, $is_name = false, $all_words = false) {
		if(is_array($str)){
//			var_dump($str);exit;
			return $str;
			//TODO Fix this
		}
		// exceptions to standard case conversion
		if ($is_name) {
			$all_uppercase = 'MD';
			$all_lowercase = 'De La|De Las|Der|Van De|Van Der|Vit De|Von|Or|And|D|Del';
			$str = preg_replace_callback("/\\b(\\w)/u", function ($matches) {
				return strtoupper($matches[1]);
			}, strtolower(trim($str)));
		} else if (!$all_words){
			//if only the first word is to be capitalised
			$str_array = explode(" ",$str);
			$first_word = array_shift($str_array);
			$str = ucfirst(strtolower(trim($str)));
			if(is_array(self::ALL_UPPERCASE)){
				$all_uppercase = '';
				foreach(self::ALL_UPPERCASE as $uc){
					if($first_word == strtolower($uc)){
						$str = strtoupper($first_word)." ".implode(" ",$str_array);
					}
					$all_uppercase .= strtolower($uc).'|';
					//set them all to lowercase
				}
			}
			if(is_array(self::ALL_LOWERCASE)){
				$all_lowercase = '';
				foreach(self::ALL_LOWERCASE as $uc){
					if($first_word == strtolower($uc)){
						$str = strtolower($first_word)." ".implode(" ",$str_array);
					}
					$all_lowercase .= strtolower($uc).'|';
					//set them all to lowercase
				}
			}
		} else {
			// addresses, essay titles ... and anything else
			if(is_array(self::ALL_UPPERCASE)){
				foreach(self::ALL_UPPERCASE as $uc){
					$all_uppercase .= ucfirst(strtolower($uc)).'|';
				}
			}
			if(is_array(self::ALL_LOWERCASE)){
				foreach(self::ALL_LOWERCASE as $uc){
					$all_lowercase .= ucfirst($uc).'|';
				}
			}
			// captialize all first letters
			//$str = preg_replace('/\\b(\\w)/e', 'strtoupper("$1")', strtolower(trim($str)));
			$str = preg_replace_callback("/\\b(\\w)/u", function ($matches) {
				return strtoupper($matches[1]);
			}, strtolower(trim($str)));
		}
		# Defaults
		$prefixes = "Mc";
		$suffixes = "'S";

		if ($all_uppercase) {
			// capitalize acronymns and initialisms e.g. PHP
			//$str = preg_replace("/\\b($all_uppercase)\\b/e", 'strtoupper("$1")', $str);
			$str = preg_replace_callback("/\\b($all_uppercase)\\b/u", function ($matches) {
				return strtoupper($matches[1]);
			}, $str);
		}
		if ($all_lowercase) {
			// decapitalize short words e.g. and
			if ($is_name) {
				// all occurences will be changed to lowercase
				//$str = preg_replace("/\\b($all_lowercase)\\b/e", 'strtolower("$1")', $str);
				$str = preg_replace_callback("/\\b($all_lowercase)\\b/u", function ($matches) {
					return strtolower($matches[1]);
				}, $str);
			} else {
				// first and last word will not be changed to lower case (i.e. titles)
				//$str = preg_replace("/(?<=\\W)($all_lowercase)(?=\\W)/e", 'strtolower("$1")', $str);
				$str = preg_replace_callback("/(?<=\\W)($all_lowercase)(?=\\W)/u", function ($matches) {
					return strtolower($matches[1]);
				}, $str);
			}
		}
		if ($prefixes) {
			// capitalize letter after certain name prefixes e.g 'Mc'
			//$str = preg_replace("/\\b($prefixes)(\\w)/e", '"$1".strtoupper("$2")', $str);
			$str = preg_replace_callback("/\\b($prefixes)(\\w)/u", function ($matches) {
				return "${matches[1]}".strtoupper($matches[2]);
			}, $str);
		}
		if ($suffixes) {
			// decapitalize certain word suffixes e.g. 's
			//$str = preg_replace("/(\\w)($suffixes)\\b/e", '"$1".strtolower("$2")', $str);
			$str = preg_replace_callback("/(\\w)($suffixes)\\b/u", function ($matches) {
				return "${matches[1]}".strtolower($matches[2]);
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
	 * @return mixed
	 */
	static function title($string, $is_name = false, $all_words = false){
		return self::capitalise_name(str_replace("_", " ", $string), $is_name, $all_words);
	}

	/**
	 * Takes a float or decimal and converts it to a percentage string,
	 * suffixed with the % sign.
	 *
	 * @param     $int_fraction float
	 * @param int $decimals int The number of decimal points to include
	 *
	 * @return string
	 */
	static function percent($int_fraction, $decimals = 0){
		$int = round($int_fraction * 100, $decimals);
		return "{$int}%";
	}

	/**
	 * Returns a readable method backtrace, like so:
	 * <code>
	 * [3176] whitelabel.php->load_ajax_call([{}]);
	 * [  28] error_log.php->unresolved([{}]);
	 * </code>
	 *
	 * Just by running this line at whichever point the backtrace is required
	 * <code>str::backtrace();</code>
	 */
	static function backtrace($return = NULL){
		$steps = [];
		array_walk(debug_backtrace(), function($a) use (&$steps) {
			$steps[] = "{$a['function']}(".json_encode($a['args']).");\r\n[".str_pad($a['line'],4, " ", STR_PAD_LEFT)."] ".basename($a['file'])."->";
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
	 * Backtraces the function and gives dumps the trail
	 * <code>
	 * str::trace();
	 * </code>
	 * @return mixed
	 */
	static function trace(){
		array_walk(debug_backtrace(),create_function('$a,$b','print "{$a[\'function\']}()(".basename($a[\'file\']).":{$a[\'line\']});\r\n";'));
		exit;
	}

	/**
	 * Formats strings and ensures that they won't break SQL.
	 * If a string is not a float, int or string,
	 * it will be outright rejected.
	 *
	 * <code>
	 * str::i($string);
	 * </code>
	 *
	 * or if HTML is accepted
	 *
	 * <code>
	 * str::i($html_string, TRUE);
	 * </code>
	 *
	 * @param float|int|string $i
	 * @param bool             $html_accepted
	 *
	 * @return mixed|string
	 */
	public static function i($i, $html_accepted = NULL){
		if(!is_string($i) && !is_int($i) && !is_float($i)){
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
	 * @link http://php.net/manual/en/function.mysql-real-escape-string.php#101248
	 * @param float|int|string $inp
	 *
	 * @return float|int|string
	 */
	public static function mysql_escape_mimic($inp) {
		if(is_array($inp))
			return array_map(__METHOD__, $inp);

		if(!empty($inp) && is_string($inp)) {
			return str_replace(array('\\', "'", '"', "\x1a"), array('\\\\', "\\'", '\\"', '\\Z'), $inp);
		}

		return $inp;
	}

	/**
	 * Generate a random token of $length character length.
	 *
	 * @param int $length
	 *
	 * @return string
	 */
	public static function token($length = 32){
		try{
			return bin2hex(random_bytes(round($length/2)));
		}
		catch(\Exception $e){
			return substr(md5(rand()), 0, $length);
		}
	}

	/**
	 * Given an array of rel_table/id and action, and an array of vars,
	 * creates a hash string and returns it.
	 *
	 * @param array $array
	 * @param bool  $urlencoded If set to yes, will urlencode the hash string
	 *
	 * @return string
	 */
	static function generate_uri($array, $urlencoded = NULL){

		# If an array is given (most common)
		if(is_array($array)){
			extract($array);
			$hash = "{$rel_table}/{$rel_id}/{$action}";
		}

		# If a string is given (less common)
		if (is_string($array)){
			$hash = $array;
		}

		# If there are vars (in the array)
		if(is_array($vars)){
			if (count($vars) == count($vars, COUNT_RECURSIVE)){
				//if the vars array is NOT multi-dimensional
				foreach($vars as $key => $val){
					if(!$val){
						continue;
					}
					$hash .= "/$key/$val";
				}
			} else {
				//if the vars array _is_ multidimensional
				$hash .= "/".json_encode($vars);
				//Encode the entire vars array as a JSON string
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
			 */
		}

		# Return, URL encoding optional
		return $urlencoded ? self::urlencode($hash) : $hash;
	}

	/**
	 * Safe URL encoding.
	 *
	 * Only URL encodes if the string needs it, or hasn't already been encoded.
	 *
	 * @param $str
	 *
	 * @return string
	 */
	static function urlencode($str){
		return strpos($str,'/') !== false ? urlencode($str) : $str;
	}

	/**
	 * Given a url + optional key/vals, generate a URL.
	 *
	 * Not to confused with a hash URI.
	 *
	 * @param $start
	 * @param $hash_array
	 *
	 * @return string
	 */
	static function generate_url($url, $hash_array = NULL){
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
	 * @return array
	 */
	static function explode($delimiters, $string){
		if(!is_array(($delimiters)) && !is_array($string)){
			//if neither the delimiter nor the string are arrays
			return explode($delimiters,$string);
		} else if(!is_array($delimiters) && is_array($string)) {
			//if the delimiter is not an array but the string is
			foreach($string as $item){
				foreach(explode($delimiters, $item) as $sub_item){
					$items[] = $sub_item;
				}
			}
			return $items;
		} else if(is_array($delimiters) && !is_array($string)) {
			//if the delimiter is an array but the string is not
			$string_array[] = $string;
			foreach($delimiters as $delimiter){
				$string_array = self::explode($delimiter, $string_array);
			}
			return $string_array;
		}
	}

	/**
	 * Searches an array of needles in a string haystack
	 * @param string $haystack
	 * @param array  $needle
	 * @param int    $offset
	 *
	 * @return bool
	 * @link http://stackoverflow.com/questions/6284553/using-an-array-as-needles-in-strpos
	 */
	public static function strposa($haystack, $needle, $offset=0) {
		if(!is_array($needle)) $needle = array($needle);
		foreach($needle as $query) {
			if(strpos($haystack, $query, $offset) !== false) return true; // stop on first true result
		}
		return false;
	}

	/**
	 * Replace something _once_.
	 *
	 * @param string $needle
	 * @param string $replace
	 * @param string $haystack
	 * @param bool $last if set to TRUE, will replace the *LAST* instance,
	 *                   instead of the default first
	 *
	 * @return mixed
	 *@link https://stackoverflow.com/a/1252710/429071
	 */
	static function str_replace_once($needle, $replace, $haystack, $last = NULL){
		if ($last){
			$pos = strrpos($haystack,$needle);
		} else {
			$pos = strpos($haystack,$needle);
		}

		if ($pos !== false) {
			return substr_replace($haystack,$replace,$pos,strlen($needle));
		}

		return $haystack;
	}

	/**
	 * Given a nubmer, assumed to be bytes,
	 * returns the corresponding value in B-TB,
	 * with suffix.
	 *
	 * @param int $bytes A number of bytes
	 * @param int $precision How precise to represent the number
	 *
	 * @return string
	 */
	static function bytes($bytes, $precision = 2) {
		$units = array('B', 'KB', 'MB', 'GB', 'TB');

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
	static function getScriptTag($script){
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
	static function get_array_depth($array) {
		$depth = 0;
		$iteIte = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($array));

		foreach ($iteIte as $ite) {
			$d = $iteIte->getDepth();
			$depth = $d > $depth ? $d : $depth;
		}

		return $depth;
	}

	/**
	 * Returns an array of (attribute) values.
	 *
	 * @param array|string|bool $attr An array or string of values to be added to the attribute. If set to false, will override $defaults.
	 * @param array|string $defaults The default values of the attribute.
	 * @param array|string $only_attr If set, will override both $attr and $defaults, this includes if it's set to false.
	 *
	 * @return array|null
	 */
	static function getAttrArrray($attr = NULL, $defaults = NULL, $only_attr = NULL){
		if($only_attr === false){
			return [];
		}

		if($only_attr){
			return is_array($only_attr) ? $only_attr : [$only_attr];
		}

		return array_merge(
			is_array($defaults)	? $defaults	: [$defaults],
			is_array($attr) 		? $attr 	: [$attr]
		);
	}

	/**
	 * Given an attribute and a value, returns a string that can be fed into a tag.
	 * If $attr = "key" and $val = "value", will return:
	 * <code>
	 * key="value"
	 * </code>
	 * @param      $attr
	 * @param mixed $val Can be a string, can be an array
	 * @param null $if_null Replacement value if the $val variable is empty (string length = 0)
	 *
	 * @return bool|string
	 */
	static function getAttrTag($attr, $val, $if_null = NULL){
		if(is_array($val)){
			$val_array = $val;
			unset($val);
			if(str::isNumericArray($val_array)){
				//if keys don't matter
				array_walk_recursive($val_array, function($a) use (&$flat_val_array) { $flat_val_array[] = $a; });
				//flatten the potentially multidimensional array
				$val = implode(" ", array_unique(array_filter($flat_val_array)));
				//remove empty and duplicate values, and then translate the array to a string
			} else {
				//if keys do matter
				foreach($val_array as $k => $v){
					$val .= "{$k}:{$v};";
				}
			}
		}

		if(!strlen(trim($val))){
			//if there is no visible val
			if($if_null){
				//if there is a replacement
				$val = $if_null;
			} else {
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
	 * Creates a random ID to give to divs.
	 * The format is `prefix_n` where N is a number generated by the `rand()` function.
	 * The prefix is optional, and will be formatted to fit jQuery + PHP formatting restrictions,
	 * meaning only alphanumeric characters are allowed.
	 *
	 * @param string $prefix
	 *
	 * @return string
	 */
	public static function id($prefix = NULL){
		$prefix = $prefix ?: "id";
		$prefix = preg_replace("/[^A-Za-z0-9]/", '_', $prefix);
		return "{$prefix}_".rand();
	}

	/**
	 * Returns a green check if $value is TRUE.
	 * Returns a red cross if $cross is TRUE and $value is FALSE.
	 *
	 * @param      $value
	 * @param bool $cross
	 *
	 * @return string
	 */
	static function check($value, $cross=false){
		if($value){
			return <<<EOF
<i class="fa fa-check fa-fw text-success" aria-hidden="true"></i>
EOF;
		}
		if($cross){
			return <<<EOF
<i class="fa fa-times fa-fw text-danger" aria-hidden="true"></i>
EOF;
		}
		return '';
	}

	/**
	 * Given an mySQL datetime value, will return a HTML string with JavaScript
	 * that will display the amount of time *ago* that timestap was.
	 * The amount of time will change as time passes.
	 *
	 * @param datetime $datetime_or_time mySQL datetime value
	 * @param bool $future If set to to true will also show future times
	 *
	 * @return string
	 * @throws \Exception
	 */
	static function ago($datetime_or_time, $future = NULL){
		if(!$datetime_or_time){
			return '';
		} else if (is_object($datetime_or_time)){
			$then = clone $datetime_or_time;
			$datetime_or_time = $then->format("Y-m-d H:i:s");
		} else {
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
	 *
	 * Output:
	 * <code>
	 * 4 months ago
	 * 4 months, 2 weeks, 3 days, 1 hour, 49 minutes, 15 seconds ago
	 * </code>
	 *
	 * The output is static.
	 *
	 * @param      $datetime
	 * @param bool $full
	 *
	 * @link https://stackoverflow.com/a/18602474/429071
	 * @return string
	 */
	static function time_elapsed_string($datetime, $full = false) {
		$now = new \DateTime;
		$ago = new \DateTime($datetime);
		$diff = $now->diff($ago);

		$diff->w = floor($diff->d / 7);
		$diff->d -= $diff->w * 7;

		$string = array(
			'y' => 'year',
			'm' => 'month',
			'w' => 'week',
			'd' => 'day',
			'h' => 'hour',
			'i' => 'minute',
			's' => 'second',
		);
		foreach ($string as $k => &$v) {
			if ($diff->$k) {
				$v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
			} else {
				unset($string[$k]);
			}
		}

		if (!$full) $string = array_slice($string, 0, 1);
		return $string ? implode(', ', $string) . ' ago' : 'just now';
	}

	/**
	 * Returns a string of how many days between then and now.
	 *
	 * @param      $date mySQL date formatted string (YYYY-MM-DD)
	 * @param bool $string if TRUE, the function may return a string variable (n days, yesterday, tomorrow, etc), if FALSE, will always just return a number
	 *
	 * @return bool|string
	 */
	static function days($date, $string = false){
		if(!$date){
			return false;
		}
		$then = \DateTime::createFromFormat("Y-m-d",$date);
		$now = new \DateTime();
		$days_away = (int)$then->diff($now)->format("%r%a");
		if(!$string){
			return $days_away;
		}
		switch($days_away){
		case -1: return "yesterday"; break;
		case 1: return "tomorrow"; break;
		default: return "{$days_away} days"; break;
		}
	}

	/**
	 * Format a float as money.
	 *
	 * @param      $amt
	 * @param bool $pad
	 * @param int  $decimals
	 *
	 * @return bool|string
	 */
	static function money($amt, $pad = false, $decimals = 2){
		if($amt === false){
			return false;
		}
		if($pad){
			return "<xmp style=\"font-family:'Roboto Mono',monospace;margin: 0;\">£".str_pad(number_format($amt, $decimals, '.', ','),$pad, " ", STR_PAD_LEFT)."</xmp>";
		}
		return "<span style=\"font-family:'Roboto Mono',monospace;\">£".number_format($amt, $decimals, '.', ',')."</span>";
	}

	/**
	 * Format a float as readable number.
	 *
	 * @param      $amt
	 * @param bool $pad
	 * @param int  $decimals
	 *
	 * @return bool|string
	 */
	static function number($amt, $pad = false, $decimals = 0){
		if(!$amt && !is_float($amt)){
//			var_dump($amt);
			return false;
		}
		if($pad){
			return "<xmp style=\"font-family:'Roboto Mono',monospace;margin: 0;display: unset;\">".str_pad(number_format($amt, $decimals, '.', ','),$pad, " ", STR_PAD_LEFT)."</xmp>";
		}
		return "<span style=\"font-family:'Roboto Mono',monospace;\">".number_format($amt, $decimals, '.', ',')."</span>";
	}

	/**
	 * Format any text as monospaced.
	 *
	 * @param      $str
	 * @param bool $upper
	 *
	 * @return string
	 */
	static function monospace($str,$upper = false){
		if($upper){
			$str = strtoupper($str);
		}
		return "<span style=\"font-family:'Roboto Mono',monospace;font-size:smaller;\">{$str}</span>";
	}

	/**
	 * Return text strings in a "raw" format.
	 *
	 * @param string|array $str
	 * @param bool         $crop If set to TRUE, will crop the output length.
	 *
	 * @return string
	 */
	public static function pre($str, $crop = NULL){
		if(is_array($str)){
			$str = var_export($str, true);
		}
//		$str = htmlentities($str);
		if(!$crop){
			return /** @lang HTML */<<<EOF
<xmp style="
	font-family: 'Roboto Mono', monospace;
	font-size: x-small;
	white-space: pre-wrap;
">{$str}</xmp>
EOF;
		}

		return /** @lang HTML */<<<EOF
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

	/**
	 * Returns FALSE is array is numeric (sequential, 0 to n row keys), TRUE otherwise.
	 *
	 * @link https://stackoverflow.com/a/173479/429071
	 * @param array $arr
	 *
	 * @return bool
	 */
	static function isAssociativeArray ($arr) {
		if (!is_array($arr)) return false;
		if (array() === $arr) return false;
		return array_keys($arr) !== range(0, count($arr) - 1);
	}

	/**
	 * Checks to see whether an array has sequential numerical keys (only),
	 * starting from 0 to n, where n is the array count minus one.
	 *
	 * The opposite of `isAssociativeArray()`.
	 *
	 * @link https://codereview.stackexchange.com/questions/201/is-numeric-array-is-missing/204
	 *
	 * @param $arr
	 *
	 * @return bool
	 */
	static function isNumericArray ($arr) {
		if (!is_array($arr)) return false;
		return array_keys($arr) === range(0, (count($arr) - 1));
	}

	/**
	 * Given an array, returns a human readable XML string.
	 *
	 * @param $array array A standard PHP array that you want to convert to XML.
	 * @param $root string The root bracket that you want to enclose the XML in.
	 *
	 * @return string A human readable XML string.
	 */
	public static function xmlify($array, $root){
		$xml = self::array_to_xml($array, new \SimpleXMLElement($root))->asXML();
		$dom = new \DOMDocument;
		$dom->preserveWhiteSpace = FALSE;
		$dom->loadXML($xml);
		$dom->formatOutput = TRUE;
		$xml_string = $dom->saveXml($dom->documentElement);
		return $xml_string;
	}

	/**
	 * Returns an array as a CSV string with the keys as a header row.
	 *
	 * @param $a
	 *
	 * @return bool
	 */
	public static function get_array_as_csv($array){
		$f = fopen('php://memory', 'r+');
		# Header row
		fputcsv($f, array_keys(array_shift($array)));
		# Data rows
		foreach ($array as $fields) {
			fputcsv($f, $fields);
		}
		rewind($f);
		$csv_line = stream_get_contents($f);
		return rtrim($csv_line);
	}

	/**
	 * AU for when you want to capitalise the first letter (only)
	 * because it's placed in the beginning of a sentance.
	 * @param type $input
	 * @param type $count
	 * @return type
	 */
	public static function AU($input, $count=1){
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
	public static function AN($input, $count=1) {
		return self::A($input, $count);
	}

	public static function A($input, $count=1) {
		$matches = array();
		$matchCount = preg_match("/\A(\s*)(?:an?\s+)?(.+?)(\s*)\Z/i", $input, $matches);
		list($all, $pre, $word, $post) = $matches;
		if(!$word)
			return $input;
		$result = self::_indef_article($word, $count);
		return $pre.$result.$post;
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

	private static function _indef_article($word, $count) {
		if($count != 1) // TO DO: Check against $PL_count_one instead
			return "$count $word";

		# HANDLE USER-DEFINED VARIANTS
		// TO DO

		# HANDLE NUMBERS IN DIGIT FORM (1,2 …)
		#These need to be checked early due to the methods used in some cases below

		#any number starting with an '8' uses 'an'
		if(preg_match("/^[8](\d+)?/", $word))					return "an $word";

		#numbers starting with a '1' are trickier, only use 'an'
		#if there are 3, 6, 9, … digits after the 11 or 18

		#check if word starts with 11 or 18
		if(preg_match("/^[1][1](\d+)?/", $word) || (preg_match("/^[1][8](\d+)?/", $word))) {

			#first strip off any decimals and remove spaces or commas
			#then if the number of digits modulus 3 is 2 we have a match
			if(strlen(preg_replace(array("/\s/", "/,/", "/\.(\d+)?/"), '', $word))%3 == 2) return "an $word";
		}

		# HANDLE ORDINAL FORMS
		if(preg_match("/^(".self::$A_ordinal_a.")/i", $word)) 		return "a $word";
		if(preg_match("/^(".self::$A_ordinal_an.")/i", $word))     	return "an $word";

		# HANDLE SPECIAL CASES

		if(preg_match("/^(".self::$A_explicit_an.")/i", $word))     	return "an $word";
		if(preg_match("/^[aefhilmnorsx]$/i", $word))     	return "an $word";
		if(preg_match("/^[bcdgjkpqtuvwyz]$/i", $word))     	return "a $word";

		# HANDLE ABBREVIATIONS

		if(preg_match("/^(".self::$A_abbrev.")/x", $word))     		return "an $word";
		if(preg_match("/^[aefhilmnorsx][.-]/i", $word))     	return "an $word";
		if(preg_match("/^[a-z][.-]/i", $word))     		return "a $word";

		# HANDLE CONSONANTS

		#KJBJM - the way this is written it will match any digit as well as non vowels
		#But is necessary for later matching of some special cases.  Need to move digit
		#recognition above this.
		#rule is: case insensitive match any string that starts with a letter not in [aeiouy]
		if(preg_match("/^[^aeiouy]/i", $word))                  return "a $word";

		# HANDLE SPECIAL VOWEL-FORMS

		if(preg_match("/^e[uw]/i", $word))                  	return "a $word";
		if(preg_match("/^onc?e\b/i", $word))                  	return "a $word";
		if(preg_match("/^uni([^nmd]|mo)/i", $word))		return "a $word";
		if(preg_match("/^ut[th]/i", $word))                  	return "an $word";
		if(preg_match("/^u[bcfhjkqrst][aeiou]/i", $word))	return "a $word";

		# HANDLE SPECIAL CAPITALS

		if(preg_match("/^U[NK][AIEO]?/", $word))                return "a $word";

		# HANDLE VOWELS

		if(preg_match("/^[aeiou]/i", $word))			return "an $word";

		# HANDLE y... (BEFORE CERTAIN CONSONANTS IMPLIES (UNNATURALIZED) "i.." SOUND)

		if(preg_match("/^(".self::$A_y_cons.")/i", $word))	return "an $word";

		#DEFAULT CONDITION BELOW
		# OTHERWISE, GUESS "a"
		return "a $word";
	}

	/**
	 * Flatten multidimentional array.
	 *
	 *
	 * @param array $array The array to flatten
	 * @param string $glue The glue to use in the flattened array keys. Default is dot-notation.

	 * @link https://stackoverflow.com/a/10424516/429071
	 *
	 * @return array
	 */
	public static function flatten($array, $glue = '.'){
		if(!is_array($array)){
			return false;
		}
		$ritit = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($array));
		$result = array();
		foreach ($ritit as $leafValue) {
			$keys = array();
			foreach (range(0, $ritit->getDepth()) as $depth) {
				$keys[] = $ritit->getSubIterator($depth)->key();
			}
			$result[ join($glue, $keys) ] = $leafValue;
		}
		return $result;
	}

	/**
	 * Searches thru a multi-dimentional array for a key,
	 * and returns an array of values belonging to matching keys.
	 *
	 * @link https://www.experts-exchange.com/questions/27653578/search-multidimensional-array-by-key-and-return-array-value's-as-result.html
	 *
	 * @param $array
	 * @param $key
	 *
	 * @return array
	 */
	static function array_key_search($array, $key)
	{
		$results = array();

		if (is_array($array))
		{
			if (isset($array[$key]) && !is_array($array[$key]))
				$results[] = $array[$key];

			foreach ($array as $subarray)
				$results = array_merge($results, self::array_key_search($subarray, $key));
		}

		return $results;
	}

	/**
	 * Does the leg work deciding "[1 ]thing was ", or "[2 ]things were ".
	 *
	 * @param array|int $array An array of things that will be counted, or an int of the count.
	 * @param string $rel_table The name of the thing that is to be counted.
	 * @param bool $include_count If set to true, will include the count also.
	 *
	 * @return bool|mixed Returns a string if the vars have been entered correctly, otherwise FALSE.
	 */
	public static function were($array, $rel_table, $include_count = false){
		if(is_array($array)){
			$count = count($array);
		} else if(is_int($array)){
			$count = $array;
		} else {
			$count = 0;
		}
		if(!is_string($rel_table)){
			return false;
		}

		if($include_count){
			switch($count){
			case 0: return str::title("No ".str::pluralise($rel_table)." were"); break;
			case 1: return str::title("1 {$rel_table} was"); break;
			default: return str::title("{$count} ".str::pluralise($rel_table)." were"); break;
			}
		}

		switch($count){
		case 0: 	return str::pluralise($rel_table)." were"; break;
		case 1: 	return "{$rel_table} was"; break;
		default:	return str::pluralise($rel_table)." were"; break;
		}
	}

	static $plural = array(
		'/(quiz)$/i'               => "$1zes",
		'/^(ox)$/i'                => "$1en",
		'/([m|l])ouse$/i'          => "$1ice",
		'/(matr|vert|ind)ix|ex$/i' => "$1ices",
		'/(x|ch|ss|sh)$/i'         => "$1es",
		'/([^aeiouy]|qu)y$/i'      => "$1ies",
		'/(hive)$/i'               => "$1s",
		'/(?:([^f])fe|([lr])f)$/i' => "$1$2ves",
		'/(shea|lea|loa|thie)f$/i' => "$1ves",
		'/sis$/i'                  => "ses",
		'/([ti])um$/i'             => "$1a",
		'/(tomat|potat|ech|her|vet)o$/i'=> "$1oes",
		'/(bu)s$/i'                => "$1ses",
		'/(alias)$/i'              => "$1es",
		'/(octop)us$/i'            => "$1i",
		'/(ax|test)is$/i'          => "$1es",
		'/(us)$/i'                 => "$1es",
		'/s$/i'                    => "s",
		'/$/'                      => "s"
	);

	static $singular = array(
		'/(quiz)zes$/i'             => "$1",
		'/(matr)ices$/i'            => "$1ix",
		'/(vert|ind)ices$/i'        => "$1ex",
		'/^(ox)en$/i'               => "$1",
		'/(alias)es$/i'             => "$1",
		'/(octop|vir)i$/i'          => "$1us",
		'/(cris|ax|test)es$/i'      => "$1is",
		'/(shoe)s$/i'               => "$1",
		'/(o)es$/i'                 => "$1",
		'/(bus)es$/i'               => "$1",
		'/([m|l])ice$/i'            => "$1ouse",
		'/(x|ch|ss|sh)es$/i'        => "$1",
		'/(m)ovies$/i'              => "$1ovie",
		'/(s)eries$/i'              => "$1eries",
		'/([^aeiouy]|qu)ies$/i'     => "$1y",
		'/([lr])ves$/i'             => "$1f",
		'/(tive)s$/i'               => "$1",
		'/(hive)s$/i'               => "$1",
		'/(li|wi|kni)ves$/i'        => "$1fe",
		'/(shea|loa|lea|thie)ves$/i'=> "$1f",
		'/(^analy)ses$/i'           => "$1sis",
		'/((a)naly|(b)a|(d)iagno|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/i'  => "$1$2sis",
		'/([ti])a$/i'               => "$1um",
		'/(n)ews$/i'                => "$1ews",
		'/(h|bl)ouses$/i'           => "$1ouse",
		'/(corpse)s$/i'             => "$1",
		'/(us)es$/i'                => "$1",
		'/s$/i'                     => ""
	);

	static $irregular = array(
		'move'   => 'moves',
		'foot'   => 'feet',
		'goose'  => 'geese',
		'sex'    => 'sexes',
		'child'  => 'children',
		'man'    => 'men',
		'tooth'  => 'teeth',
		'person' => 'people',
		'valve'  => 'valves'
	);

	static $uncountable = array(
		'sheep',
		'fish',
		'deer',
		'series',
		'species',
		'money',
		'rice',
		'information',
		'equipment'
	);

	/**
	 * Pluralises a string.
	 *
	 * @param $string
	 * @link http://kuwamoto.org/2007/12/17/improved-pluralizing-in-php-actionscript-and-ror/
	 * @return bool|string
	 */
	public static function pluralise( $string )
	{
		// If no string is supplied
		if(!$string){
			return false;
		}

		// save some time in the case that singular and plural are the same
		if ( in_array( strtolower( $string ), self::$uncountable ) )
			return $string;


		// check for irregular singular forms
		foreach ( self::$irregular as $pattern => $result )
		{
			$pattern = '/' . $pattern . '$/i';

			if ( preg_match( $pattern, $string ) )
				return preg_replace( $pattern, $result, $string);
		}

		// check for matches using regular expressions
		foreach ( self::$plural as $pattern => $result )
		{
			if ( preg_match( $pattern, $string ) )
				return preg_replace( $pattern, $result, $string );
		}

		return $string;
	}

	public static function singularise( $string )
	{
		// save some time in the case that singular and plural are the same
		if ( in_array( strtolower( $string ), self::$uncountable ) )
			return $string;

		// check for irregular plural forms
		foreach ( self::$irregular as $result => $pattern )
		{
			$pattern = '/' . $pattern . '$/i';

			if ( preg_match( $pattern, $string ) )
				return preg_replace( $pattern, $result, $string);
		}

		// check for matches using regular expressions
		foreach ( self::$singular as $pattern => $result )
		{
			if ( preg_match( $pattern, $string ) )
				return preg_replace( $pattern, $result, $string );
		}

		return $string;
	}

	/**
	 * Returns $rel_table pluralised if $array > 1.
	 *
	 * If the <code>$include_count</code> flag is set, will also return the count.
	 *
	 * Examples:
	 * <code>
	 * $this->str->pluralise_if(3, "chair", true);
	 * //3 chairs
	 *
	 * $this->str->pluralise_if(false, "item", true);
	 * //0 items
	 *
	 * str::pluralise_if(1, "boat", false);
	 * //boat
	 * </code>
	 *
	 * @param      $array array|int|string The array to count, or int to check or string to convert to number to check
	 * @param      $rel_table string The word to pluralise
	 * @param null $include_count bool If set to TRUE, will include the count as a prefix in the string returned.
	 *
	 * @return bool|null|string|string[]
	 */
	public static function pluralise_if($array, $rel_table, $include_count = NULL, $include_is_are = NULL){
		if(is_array($array)){
			$count = count($array);
		} else if(is_int($array) && $array){
			$count = $array;
		} else if(is_string($array) && $string = preg_replace("/[^0-9\\.]/","",$array)){
			$count = $string;
		} else {
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
	 * Given an array, returns an oxford comma separated,
	 * grammatically correct list of items:
	 * <code>
	 * ["apples","oranges","bananas","pears"]
	 * </code>
	 * //Returns "apples, oranges, bananas and pears"
	 *
	 * @param        $array
	 * @param string $glue
	 * @param string $and_or
	 *
	 * @return bool|mixed|string
	 */
	public static function oxford_implode($array, $glue = ", ", $and_or = "and"){
		if(empty($array)){
			return false;
		}

		if(count($array) == 1){
			return reset($array);
		}

		$and_or = " {$and_or} ";

		if(count($array) == 2){
			return reset($array) . $and_or . end($array);
		}

		$last_element = array_pop($array);

		return implode($glue, $array).$and_or.$last_element;
	}

	/**
	 * Sanitise a string to be used as a filename.
	 *
	 *
	 * @param      $filename
	 * @param bool $beautify
	 * @link https://stackoverflow.com/a/42058764/429071
	 * @return null|string|string[]
	 */
	public static function filter_filename($filename, $beautify=true) {
		// sanitize filename
		$filename = preg_replace(
			'~
        [<>:"/\\|?*]|            # file system reserved https://en.wikipedia.org/wiki/Filename#Reserved_characters_and_words
        [\x00-\x1F]|             # control characters http://msdn.microsoft.com/en-us/library/windows/desktop/aa365247%28v=vs.85%29.aspx
        [\x7F\xA0\xAD]|          # non-printing characters DEL, NO-BREAK SPACE, SOFT HYPHEN
        [#\[\]@!$&\'()+,;=]|     # URI reserved https://tools.ietf.org/html/rfc3986#section-2.2
        [{}^\~`]                 # URL unsafe characters https://www.ietf.org/rfc/rfc1738.txt
        ~x',
			'-', $filename);
		// avoids ".", ".." or ".hiddenFiles"
		$filename = ltrim($filename, '.-');
		// optional beautification
		if ($beautify) $filename = self::beautify_filename($filename);
		// maximise filename length to 255 bytes http://serverfault.com/a/9548/44086
		$ext = pathinfo($filename, PATHINFO_EXTENSION);
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
	public static function beautify_filename($filename) {
		// reduce consecutive characters
		$filename = preg_replace(array(
			// "file   name.zip" becomes "file-name.zip"
			'/ +/',
			// "file___name.zip" becomes "file-name.zip"
			'/_+/',
			// "file---name.zip" becomes "file-name.zip"
			'/-+/'
		), '-', $filename);
		$filename = preg_replace(array(
			// "file--.--.-.--name.zip" becomes "file.name.zip"
			'/-*\.-*/',
			// "file...name..zip" becomes "file.name.zip"
			'/\.{2,}/'
		), '.', $filename);
		// lowercase for windows/unix interoperability http://support.microsoft.com/kb/100625
		$filename = mb_strtolower($filename, mb_detect_encoding($filename));
		// ".file-name.-" becomes "file-name"
		$filename = trim($filename, '.-');
		return $filename;
	}

	/**
	 * Finds degrees of mismatches between two strings, returns the degree.
	 * If strings are identical, returns 0.
	 *
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
	public static function mismatch ($a, $b, $excluded_words_case_insensitive = NULL){
		# Format the excluded words (if provided)
		if($excluded_words_case_insensitive){
			if(!is_array($excluded_words_case_insensitive)){
				$string = mb_strtolower($excluded_words_case_insensitive);
				$excluded_words[] = $string;
			} else {
				$excluded_words = array_map("mb_strtolower", $excluded_words_case_insensitive);
			}
		} else {
			$excluded_words = [];
		}

		# 0. Exact match
		$mismatch = 0;

		if($a == $b){return $mismatch;}

		# 1. Case mismatch "lowercase(a)==lowercase(b)"
		$mismatch++;

		# Ignore case from here on
		$a = mb_strtolower($a);
		$b = mb_strtolower($b);

		if($a == $b){return $mismatch;}

		# 2. Ignore unicode mismatches
		$mismatch++;

		# Decompose unicode characters
		$a = preg_replace("/\pM*/u", "", normalizer_normalize($a, \Normalizer::FORM_D));
		$b = preg_replace("/\pM*/u", "", normalizer_normalize($b, \Normalizer::FORM_D));

		if($a == $b){return $mismatch;}

		# 3. Space mismatches (everything is the same bar the number of spaces)
		$mismatch++;

		# Trim and filter away all superfluous spaces
		$a = implode(" ",array_filter(preg_split('/\s+/', $a)));
		$b = implode(" ",array_filter(preg_split('/\s+/', $b)));

		if($a == $b){return $mismatch;}

		# 4. excluded words mismatch "lowercase==lowercase, except in excluded words"
		$mismatch++;

		# Ignore the excluded words from here on
		$a = trim(str_replace($excluded_words, "", $a));
		$b = trim(str_replace($excluded_words, "", $b));

		if($a == $b){return $mismatch;}

		# 5. excluded non alphanumeric characters
		$mismatch++;

		# Trim away everything that isn't A-Z or 0-9, and extra spaces
		$a = implode(" ",array_filter(preg_split('/[^A-Za-z0-9]+/', $a)));
		$b = implode(" ",array_filter(preg_split('/[^A-Za-z0-9]+/', $b)));

		if($a == $b){return $mismatch;}

		# 6. All the same words, just in a different order
		$mismatch++;

		//@link https://stackoverflow.com/questions/21138505/check-if-two-arrays-have-the-same-values
		//@link https://stackoverflow.com/questions/18720682/sort-a-php-array-returning-new-array
		$a = implode(" ",call_user_func(function(array $a){sort($a);return $a;}, preg_split('/\s+/', $a)));
		$b = implode(" ",call_user_func(function(array $b){sort($b);return $b;}, preg_split('/\s+/', $b)));

		if($a == $b){return $mismatch;}

		# 7. omitted words mismatch "a contains b, or b contains a, but also has additional strings"
		$mismatch++;

		if(empty(array_diff(explode(" ", $a), explode(" ", $b))) || empty(array_diff(explode(" ", $b), explode(" ", $a)))){
			return $mismatch;
		}

		# 8. spelling mismatch (a==b, except 1 character)
		$mismatch++;

		if(strlen($a) > 3 && strlen($b) > 3){
			//this only applies is both strings are longer than 3 characters
			if((strlen($a) > strlen($b) ? strlen($a) : strlen($b)) - similar_text($a,$b) == 1){
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
	public static function mismatch_colour($mismatch){
		switch($mismatch){
		case 7: return "danger"; break;
		case 6: return "warning"; break;
		case 5: return "warning"; break;
		case 4: return "warning"; break;
		case 3: return "info"; break;
		case 2: return "info"; break;
		case 1: return "info"; break;
		default: return "success"; break;
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
	static function hex2hsl ($hex) {
		$hex = array($hex[0] . $hex[1], $hex[2] . $hex[3], $hex[4] . $hex[5]);
		$rgb = array_map(function ($part) {
			return hexdec($part) / 255;
		}, $hex);

		$max = max($rgb);
		$min = min($rgb);

		$l = ($max + $min) / 2;

		if ($max == $min) {
			$h = $s = 0;
		} else {
			$diff = $max - $min;
			$s = $l > 0.5 ? $diff / (2 - $max - $min) : $diff / ($max + $min);

			switch ($max) {
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

		return array($h, $s, $l);
	}

	/**
	 * Given an array of hue/saturation/lightness, returns the corresponding hex value.
	 *
	 * @param array $hsl An array containging hue, saturation, lightness.
	 *
	 * @return string A string containgin a HEX colour value (ex. FF0000 for red)
	 */
	static function hsl2hex ($hsl) {
		list($h, $s, $l) = $hsl;

		if ($s == 0) {
			$r = $g = $b = 1;
		} else {
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
	static function hue2rgb ($p, $q, $t) {
		if ($t < 0) $t += 1;
		if ($t > 1) $t -= 1;
		if ($t < 1 / 6) return $p + ($q - $p) * 6 * $t;
		if ($t < 1 / 2) return $q;
		if ($t < 2 / 3) return $p + ($q - $p) * (2 / 3 - $t) * 6;

		return $p;
	}

	/**
	 * Given RGB colour, will return corresponding HEX colour value.
	 *
	 * @param $rgb
	 *
	 * @return string
	 */
	static function rgb2hex ($rgb) {
		return str_pad(dechex($rgb * 255), 2, '0', STR_PAD_LEFT);
	}

	static function is_hex_colour($colour){
		if(!is_string($colour)){
			return false;
		}
		if(preg_match('/^#(?:[0-9a-fA-F]{3}){1,2}$/', $colour)){
			return true;
		}
		return false;
	}

	/**
	 * Get the style of button
	 * @param $a
	 *
	 * @return mixed
	 */
	static function get_style($a){
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
	static function getColour($colour, $prefix = 'text'){
		if(!$colour){
			return false;
		}
		if($translated_colour = str::translate_colour($colour)){
			return "{$prefix}-{$translated_colour}";
		} else {
			return false;
			//The default is no colour (black)
		}
	}

	/**
	 * Translates "real" colour names to Bootstrap 4 colour names.
	 * If a "real" colour name cannot be found, FALSE is returned.
	 * @param $colour
	 *
	 * @return bool|string
	 */
	static function translate_colour($colour){
		switch($colour){
		case 'custom'   : return 'custom'; break;
		case 'primary'  : return 'primary'; break;
		case 'blue'     : return 'blue'; break;
		case 'secondary': return 'secondary'; break;
		case 'grey'     : return 'gray'; break;
		case 'gray'     : return 'gray'; break;
		case 'green'    : return 'success'; break;
		case 'success'  : return 'success'; break;
		case 'warning'  : return 'warning'; break;
		case 'yellow'   : return 'warning'; break;
		case 'orange'   : return 'warning'; break;
		case 'danger'   : return 'danger'; break;
		case 'red'      : return 'danger'; break;
		case 'info'     : return 'info'; break;
		case 'light'    : return 'light'; break;
		case 'white'    : return 'light'; break;
		case 'dark'     : return 'dark'; break;
		case 'black'    : return 'dark'; break;
		case 'muted'    : return 'muted'; break;
		case 'link'     : return 'link'; break;
			/**
			 * Link isn't strictly speaking a colour,
			 * but is treated like a colour for button
			 * generation.
			 */
		default: return $colour; break;
		/**
		 * For other colours, just return the name itself.
		 */
		}
	}

	/**
	 * The approval box colours are limited.
	 *
	 * @param $colour
	 *
	 * @return bool|string
	 */
	static function translate_approve_colour($colour){
		switch($colour){
		case 'primary'  : return 'blue'; break;
		case 'blue'     : return 'blue'; break;
		case 'info'     : return 'blue'; break;
		case 'green'    : return 'green'; break;
		case 'success'  : return 'green'; break;
		case 'warning'  : return 'orange'; break;
		case 'yellow'   : return 'orange'; break;
		case 'orange'   : return 'orange'; break;
		case 'danger'   : return 'red'; break;
		case 'red'      : return 'red'; break;
		case 'purple'   : return 'purple '; break;
		case 'dark'     : return 'dark'; break;
		case 'black'    : return 'dark'; break;
		default: return false; break;
		}
	}

	/**
	 * If "approve" => "string" is included in a button config,
	 * create an approval modal based on the button config.
	 *
	 * @param $a string|array Can either be a simple sentence fragment
	 *           describing the action wanted or an array of settings,
	 *           including icons, colour, text, etc.
	 *
	 *
	 * @return bool|string
	 */
	static function get_approve_script($a){
		extract($a);

		if(!$approve){
			//If no approve modal is required
			return false;
		}

		if(is_bool($approve)){
			$message = str::title("Are you sure you want to do this?");
		} else if(is_string($approve)){
			//if just the name of the thing to be removed is given
			$message = str::title("Are you sure you want to {$approve}?");
		} else {
			extract($a['approve']);
			//can override icon/class/etc options
		}

		if(!$title){
			$title = "Are you sure?";
		}
		if(substr($title,-3) == '...'){
			//remove potential ellipsis
			$title = substr($title,0,-3);
		}

		if(is_array($hash)){
			$hash = str::generate_uri($hash, false);
			//Not sure why this was set to true, but could have a real reason
		}

		if($remove && $hash){
			$hash .= substr($hash,-1) == "/" ? "" : "/";
			$hash .=  "div/{$id}";
			$confirm = "$(\"#{$id}\").{$remove}.remove();".$confirm;
			$confirm = "window.location.hash = '#{$hash}';".$confirm;
		} else if($div && $hash){
			$hash .= substr($hash,-1) == "/" ? "" : "/";
			$hash .=  "div/{$div}";
			$confirm = "hashChange('{$hash}');";
		} else if($onclick||$onClick){
			$confirm = $onclick.$onClick;
		} else if ($hash){
			if($hash == -1){
				//only -1 works in a button context
				$confirm = "window.history.back();";
			} else {
				$confirm = "window.location.hash = '#{$hash}';";
			}
		} else if ($url) {
			$confirm = "window.location = '{$url}';";
		} else if ($type=="submit"){
			//if this is confirming a form submission
			$confirm = "$('#{$id}').closest('form').submit();
			let l = Ladda.create( document.querySelector( '#{$id}' ) );
			l.start();";
			//submit the form

		}

		$icon_class = Icon::getClass($icon);
		$type = self::translate_approve_colour($colour);
		$button_colour = self::getColour($colour, "btn");

		$message = str_replace(["\r\n","\r","\n"], " ", $message);

		return /** @lang HTML */<<<EOF
<script>
$('#{$id}').on('click', function (event) {
    event.stopImmediatePropagation();
	event.preventDefault();
	$.confirm({
		animateFromElement: false,
		escapeKey: true,
		backgroundDismiss: true,
		closeIcon: true,
		type: "{$type}",
		theme: "modern",
		icon: "{$icon_class}",
		title: "{$title}",
		content: "{$message}",
		buttons: {
			confirm: {
				text: "Yes", // text for button
				btnClass: "{$button_colour}", // class for the button
				keys: ["enter"], // keyboard event for button
				action: function(){
					{$confirm}
				}
			},
			cancel: function () {
				//Close
			},
		}
	});
});
</script>
EOF;


	}
}