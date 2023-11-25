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
	 * Converts a flat dot-nation array into a normalised
	 * multidimensional array. Checks if scalar values match
	 * any row, not just the one immediately preceding it.
	 *
	 * @param $array
	 *
	 * @return array|null
	 */
	public static function normalise($array): ?array
	{
		$row_number = 0;

		$scalar = [];
		$non_scalar = [];

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
				if(is_array($scalar[$row_number]) && ($other_row_number = array_search(implode("", $scalar[$row_number]), $scalar_row_strings)) !== false){

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
					self::normaliseChildren($row_number, $scalar, $non_scalar);
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
		self::normaliseChildren($row_number, $scalar, $non_scalar);

		return $scalar;
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

	private static function normaliseChildren(int $row_number, ?array &$scalar, ?array &$non_scalar): void
	{
		# If we have not collected any non-scalar values, pencils down
		if(!$non_scalar[$row_number - 1]){
			return;
		}

		# For each non-scalar value
		foreach($non_scalar[$row_number - 1] as $key => $val){

			# Go deeper
			$val = self::normalise($val);

			# If there was any value below, add them, otherwise, set the key value to NULL
			$scalar[$row_number - 1][$key] = @array_filter(array_map('array_filter', $val)) ? $val : NULL;
		}

		# Remove the non-scalar values of this row
		unset($non_scalar[$row_number - 1]);
		// We added them to the scalar array
	}
}