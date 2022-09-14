<?php


namespace App\Common\SQL;


use App\Common\SQL\mySQL\Common;
use App\Common\str;

/**
 * Class DotNotation
 *
 * Transforms a 2D dot-notation array table
 * to a multidimensional array.
 *
 * @package App\Common\SQL
 */
class DotNotation {

	/**
	 * Transforms dot-notation arrays to multidimensional arrays.
	 *
	 * @param array $array
	 *
	 * @return array
	 */
	private static function expandKeys(array $array): array
	{
		$result = [];
		foreach($array as $key => $value){
			if(is_array($value)){
				$value = self::expandKeys($value);
			}
			foreach(array_reverse(explode(".", $key)) as $key){
				self::removeOptionalDbPrefix($key);
				$value = [$key => $value];
			}
			$result = array_merge_recursive($result, $value);
		}
		return $result;
	}

	/**
	 * Removes the optional DB prefix from the DB::TABLE.CHILD_TABLE pattern,
	 * or even the DB::TABLE.DB::CHILD_TABLE pattern.
	 *
	 * @param $key
	 */
	private static function removeOptionalDbPrefix(&$str)
	{
		$keys = explode(".", $str);

		foreach($keys as &$key){
			# Explode they key by any ::'s
			$key_fragments = explode(Common::DB_TABLE_SEPARATOR, $key);

			# Remove any DB fragments
			if(count($key_fragments) > 1){
				//If there _are_ any DB prefixes
				array_shift($key_fragments);
				//Remove the first (database) fragment
			}

			# Fuse the bits together
			$key = implode("", $key_fragments);
		}

		$str = implode(".", $keys);
	}

	private static function isAssocArray($array)
	{
		if(!is_array($array)){
			return false;
		}
		return self::getArrayType($array) == "assoc";
		//		return ($array !== array_values($array));
	}

	private static function getArrayType(array $obj): string
	{
		$last_key = -1;
		$type = 'index';
		foreach($obj as $key => $val){
			if(!is_int($key) || $key < 0){
				return 'assoc';
			}
			if($key !== $last_key + 1){
				$type = 'sparse';
			}
			$last_key = $key;
		}
		return $type;
	}

	private static function convert(int $size): string
	{
		$unit = ['b', 'kb', 'mb', 'gb', 'tb', 'pb'];
		return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . ' ' . $unit[$i];
	}

	/**
	 * Merges multi-dimensional rows that have the same scalar values,
	 * and merges their children if the same, otherwise adds them as siblings
	 * if they are different.
	 *
	 * @param array $array
	 *
	 * @return array
	 * @link https://stackoverflow.com/a/65042103/429071
	 */
	private static function complexMerge(array $array): ?array
	{
		# Grouped items
		$result = [];
		$iterationKey = 0;

		# Loop through every item
		while(($element = array_shift($array)) !== NULL) {
			//			echo self::convert(memory_get_usage())."\r\n";

			# We're not interested in empty (keys, but no value) branches
			if(!str::array_filter_recursive($element)){
				continue;
			}

			# Save scalar values as is.
			$scalarValues = [];
			foreach($element as $k => $v){
				if(is_scalar($v) || ($v === NULL) || !self::isAssocArray($v)){
					$scalarValues[$k] = $v;
				}
			}

			/**
			 * We also want to keep the NULL values (for the sake of the keys).
			 * Finally, numerical arrays (JSON values) are also treated as string values,
			 * and ignored.
			 */

			# Save associative array values in an array
			$arrayValues = array_map(function(array $arrVal){
				return [$arrVal];
			}, array_filter($element, function($value){
				return self::isAssocArray($value);
				// We are essentially treating numerical arrays as sting values
			}));

			$arrayValuesKeys = array_keys($arrayValues);

			$result[$iterationKey] = array_merge($scalarValues, $arrayValues);

			# Compare with existing items
			for($i = 0; $i < count($array); $i++){
				# Sift out the comparison scalar values (keep the NULLs and numerical arrays (most probably JSON) here also)

				$comparisonScalarValues = [];
				foreach($array[$i] as $k => $v){
					if(is_scalar($v) || ($v === NULL) || !self::isAssocArray($v)){
						$comparisonScalarValues[$k] = $v;
					}
				}

				# If scalar values are same, add the array values to the containing arrays
				if($scalarValues === $comparisonScalarValues){

					$comparisonArrayValues = [];
					foreach($array[$i] as $k => $v){
						if(self::isAssocArray($v)){
							$comparisonArrayValues[$k] = $v;
						}
					}

					foreach($arrayValuesKeys as $arrayKey){
						$result[$iterationKey][$arrayKey][] = $comparisonArrayValues[$arrayKey];
					}

					# Remove matching item
					array_splice($array, $i, 1);
					$i--;
				}
			}

			# Go deeper
			foreach($arrayValuesKeys as $arrayKey){
				$result[$iterationKey][$arrayKey] = self::complexMerge($result[$iterationKey][$arrayKey]);
			}

			# Increment the result key
			$iterationKey++;
		}

		# If there are no rows, return NULL instead of an empty array
		if(!$result){
			return NULL;
		}

		return $result;
	}

	/**
	 * Normalise a 2D dot-notation table.
	 *
	 * @param $array
	 *
	 * @return array|null
	 */
	public static function normalise($array): ?array
	{
		return self::normalise3($array);

		return self::normalise2($array);

		$array = self::expandKeys($array);
		$array = self::complexMerge($array);
		return $array;
	}

	/**
	 * Converts a flat dot-nation array into a normalised
	 * multi-dimensional array.
	 *
	 * @param $array
	 *
	 * @return array|null
	 */
	private static function normalise2($array): ?array
	{
		$row_number = 0;

		# For each row of the array
		foreach($array as $single_row){

			# For each key (column) of a single row
			foreach($single_row as $key => $val){
				# Remove the optional DB prefix
				self::removeOptionalDbPrefix($key);

				# Explode the key, but only the first level
				$keys = explode(".", $key, 2);

				# If the key has levels, separate out the values
				if($keys[1]){
					# Into those values that belong to a child array
					$non_scalar[$row_number][$keys[0]][0][$keys[1]] = $val;
				}
				else {
					# And those that are scalar values belonging to this row
					$scalar[$row_number][$key] = $val;
				}
			}

			# If this is not the first row
			if($row_number){

				# If the scalar values of this row are the same as the previous row
				if($scalar[$row_number] == $scalar[$row_number - 1]){

					# Remove this row
					unset($scalar[$row_number]);

					# If we have collected non-scalar values
					if($non_scalar[$row_number - 1]){

						# For each non-scalar value
						foreach($non_scalar[$row_number - 1] as $key => $val){

							# If the value is the same as the value of the previous row
							if($non_scalar[$row_number - 1][$key] == $non_scalar[$row_number][$key]){

								# Ignore it
								continue;
							}

							# Otherwise, combine the non-scalar values of this row with those of the previous row
							$non_scalar[$row_number - 1][$key] = array_merge($non_scalar[$row_number - 1][$key], $non_scalar[$row_number][$key]);
						}

						# Remove the non-scalar values of this row
						unset($non_scalar[$row_number]);
						// We merged any new values
					}

					# Go to the next row
					continue;
				}

				# If the scalar values are not the same as the previous row
				else {
					// Meaning we're on a "new" row

					# Normalise the non-scalar value children
					self::normaliseChildren($row_number, $scalar, $non_scalar);
				}
			}

			# Keep count of actual rows
			$row_number++;
		}

		# Finally, merge any left-over non-scalar values
		self::normaliseChildren($row_number, $scalar, $non_scalar);

		return $scalar;
	}

	private static function normaliseChildren(int $row_number, ?array &$scalar, ?array &$non_scalar): void
	{
		# If we have not collected any non-scalar values, pencils down
		if(!$non_scalar[$row_number - 1]){
			return;
		}

		# For each non-scalar value
		foreach($non_scalar[$row_number - 1] as $key => $val){

			# Go deeper
			$val = self::normalise2($val);

			# If there was any value below, add them, otherwise, set the key value to NULL
			$scalar[$row_number - 1][$key] = @array_filter(array_map('array_filter', $val)) ? $val : NULL;
		}

		# Remove the non-scalar values of this row
		unset($non_scalar[$row_number - 1]);
		// We added them to the scalar array
	}

	/**
	 * Converts a flat dot-nation array into a normalised
	 * multi-dimensional array. Checks if scalar values match
	 * any row, not just the one immediately preceding it.
	 *
	 * @param $array
	 *
	 * @return array|null
	 */
	public static function normalise3($array): ?array
	{
		$row_number = 0;

		# For each row of the array
		foreach($array as $single_row){

			# For each key (column) of a single row
			foreach($single_row as $key => $val){
				# Remove the optional DB prefix
				self::removeOptionalDbPrefix($key);

				# Explode the key, but only the first level
				$keys = explode(".", $key, 2);

				# If the key has levels, separate out the values
				if($keys[1]){
					# Into those values that belong to a child array
					$non_scalar[$row_number][$keys[0]][0][$keys[1]] = $val;
				}
				else {
					# And those that are scalar values belonging to this row
					$scalar[$row_number][$key] = $val;
				}
			}

			# If this is not the first row
			if($row_number){

				# If the scalar values of this row are the same as a previous row
				if(($other_row_number = array_search(implode("", $scalar[$row_number]), $scalar_row_strings)) !== false){

					# Remove this (duplicate) row
					unset($scalar[$row_number]);

					# If we have collected non-scalar values in the previous row
					if($non_scalar[$other_row_number]){

						# For each non-scalar value
						foreach($non_scalar[$other_row_number] as $key => $val){

							# If the value is the same as the value of the previous row
							if($non_scalar[$other_row_number][$key] == $non_scalar[$row_number][$key]){

								# Ignore it
								continue;
							}

							# Otherwise, combine the non-scalar values of this row with those of the previous row
							$non_scalar[$other_row_number][$key] = array_merge($non_scalar[$other_row_number][$key], $non_scalar[$row_number][$key]);
						}

						# Remove the non-scalar values of this row
						unset($non_scalar[$row_number]);
						// We merged any new values
					}

					# Go to the next row
					continue;
				}

				# If the scalar values are not the same as the previous row
				else {
					// Meaning we're on a "new" row

					# Normalise the non-scalar value children
					self::normaliseChildren3($row_number, $scalar, $non_scalar);
				}
			}

			# Store all scalar values as strings so that they can be matched even when rows are not next to each other
			if($scalar[$row_number]){
				$scalar_row_strings[$row_number] = implode("", $scalar[$row_number]);
			}

			# Keep count of actual rows
			$row_number++;
		}

		# Finally, merge any left-over non-scalar values
		self::normaliseChildren3($row_number, $scalar, $non_scalar);

		return $scalar;
	}

	private static function normaliseChildren3(int $row_number, ?array &$scalar, ?array &$non_scalar): void
	{
		# If we have not collected any non-scalar values, pencils down
		if(!$non_scalar[$row_number - 1]){
			return;
		}

		# For each non-scalar value
		foreach($non_scalar[$row_number - 1] as $key => $val){

			# Go deeper
			$val = self::normalise3($val);

			# If there was any value below, add them, otherwise, set the key value to NULL
			$scalar[$row_number - 1][$key] = @array_filter(array_map('array_filter', $val)) ? $val : NULL;
		}

		# Remove the non-scalar values of this row
		unset($non_scalar[$row_number - 1]);
		// We added them to the scalar array
	}
}