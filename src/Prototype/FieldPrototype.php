<?php

namespace App\Common\Prototype;

class FieldPrototype {
	public static function hiddenFields(?array $a, ?array $keys = NULL): ?array
	{
		if(!$keys){
			return NULL;
		}

		foreach($keys as $key){
			$fields[] = [
				"type" => "hidden",
				"name" => $key,
				"value" => $a[$key]
			];
		}

		return $fields;
	}
}