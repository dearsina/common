<?php

namespace App\Common\Prototype;

class FieldPrototype {
	/**
	 * If a key ends with [], it will assume the value is an array,
	 * and store it as JSON.
	 *
	 * @param array|null $a
	 * @param array|null $keys
	 *
	 * @return array|null
	 */
	public static function hiddenFields(?array $a, ?array $keys = NULL): ?array
	{
		if(!$keys){
			return NULL;
		}

		foreach($keys as $key){
			if(substr($key, -2) == "[]"){
				if(is_array($a[substr($key, 0, -2)])){
					$a[$key] = json_encode($a[substr($key, 0, -2)]);
				}
			}
			if(!isset($a[$key])){
				continue;
			}
			$fields[] = [
				"type" => "hidden",
				"name" => $key,
				"value" => $a[$key]
			];
		}

		return $fields;
	}
}