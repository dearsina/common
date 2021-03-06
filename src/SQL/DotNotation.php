<?php


namespace App\Common\SQL;


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
	 * Removes the optional DB prefix from the DB:TABLE.CHILD_TABLE pattern.
	 *
	 * @param $key
	 */
	private static function removeOptionalDbPrefix(&$key)
	{
		# Explode they key by any :'s
		$key_fragments = explode(":", $key);

		# Remove any DB fragments
		if(count($key_fragments) > 1){
			//If there _are_ any :
			array_shift($key_fragments);
			//Remove the first (database) fragment
		}

		# Fuse the bits together
		$key = implode("", $key_fragments);
	}

	private static function isAssocArray($array){
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
		foreach( $obj as $key => $val ){
			if( !is_int( $key ) || $key < 0 ){
				return 'assoc';
			}
			if( $key !== $last_key + 1 ){
				$type = 'sparse';
			}
			$last_key = $key;
		}
		return $type;
	}

	private static function convert(int $size): string
	{
		$unit=array('b','kb','mb','gb','tb','pb');
		return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
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
//			$scalarValues = array_filter($element, function($value){
//				return is_scalar($value) || $value === NULL || !self::isAssocArray($value);
//			});
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

//				$comparisonScalarValues = array_filter($array[$i], function($value){
//					return is_scalar($value) || $value === NULL || !self::isAssocArray($value);
//				});
				$comparisonScalarValues = [];
				foreach($array[$i] as $k => $v){
					if(is_scalar($v) || ($v === NULL) || !self::isAssocArray($v)){
						$comparisonScalarValues[$k] = $v;
					}
				}

				# If scalar values are same, add the array values to the containing arrays
				if($scalarValues === $comparisonScalarValues){

//					$comparisonArrayValues = array_filter($array[$i], function($value){
//						return self::isAssocArray($value);
//						//We only want the associative arrays (not the numerical ones)
//					});
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
		$array = self::expandKeys($array);
		$array = self::complexMerge($array);
		//		$array = self::mergeRows($array);
		return $array;
	}
}