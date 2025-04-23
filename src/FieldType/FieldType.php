<?php


namespace App\Common\FieldType;


use App\Common\Prototype\ModalPrototype;
use App\Common\SQL\Info\Info;
use App\Common\str;
use App\UI\Icon;

class FieldType extends ModalPrototype {
	/**
	 * @return Card
	 */
	public function card()
	{
		return new Card();
	}

	/**
	 * @return Modal
	 */
	public function modal()
	{
		return new Modal();
	}

	public function rowHandler(array $cols, ?array $a = []): array
	{
		extract($a);

		# For the order function
		$row["id"] = $cols["{$rel_table}_id"];

		$row['icon'] = [
			"sortable" => false,
			"sm" => 1,
			"style" => [
				"margin" => "0.3rem 0",
			],
			"icon" => $cols['icon'],
		];

		if($cols['settings']['display_only']){
			$badges[] = [
				"icon" => "eye",
				"colour" => "secondary",
				"basic" => true,
				"alt" => "Display only",
			];
		}

		if($cols['settings']['form_field']){
			$badges[] = [
				"icon" => Icon::get("form_field"),
				"colour" => "primary",
				"basic" => true,
				"alt" => "Can be used as a form field type",
			];
		}

		if($cols['settings']['form_value']){
			$badges[] = [
				"icon" => Icon::get("form_value"),
				"colour" => "green",
				"basic" => true,
				"alt" => "Can be used as a form value type",
			];
		}

		$row['Type'] = [
			"accordion" => [
				"header" => [
					"title" => $cols['title'],
					"class" => "text-title",
					"badge" => $badges,
				],
				"body" => [
					"html" => $cols['desc'],
					"class" => "text-body",
				],
			],
		];

		$row['HTML'] = [
			"html" => $cols['name'],
			"sm" => 2,
		];

		$row[''] = [
			"sortable" => false,
			"button" => $this->getRowButtons($cols, $a),
			"sm" => 2,
		];

		return $row;
	}

	/**
	 * Identifies if the field type is display only,
	 * meaning the field doesn't actually contain a value
	 * that can be changed by a user.
	 *
	 * Can include an array of exclusions, names of types
	 * that will be ignored even if they're display only.
	 *
	 * @param string|null $field_type_id
	 * @param array|null  $exclusions
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public static function isDisplayOnly(?string $field_type_id, ?array $exclusions = NULL): bool
	{
		if(!$field_type_id){
			return false;
		}

		# Get all the display only fields
		$display_only_fields = FieldType::getFieldTypeOptions([
			"display_only" => true,
		]);

		# If the field is not display only
		if(!$display_only_fields[$field_type_id]){
			return false;
		}

		# If the field is display only, but is excluded
		if($exclusions && in_array($display_only_fields[$field_type_id], $exclusions)){
			return false;
		}

		# Otherwise, ya, it's a display only field
		return true;
	}

	public static function getFieldTypeIcon(?string $name): ?string
	{
		if(!$name){
			return NULL;
		}
		
		$field_types = self::get();
		return $field_types[$name]['icon'];
	}

	/**
	 * Given a field type name, return the field type ID.
	 *
	 * @param string $name
	 *
	 * @return string|null
	 * @throws \Exception
	 */
	public static function getFieldTypeIdFromName(string $name): ?string
	{
		$field_types = self::get();
		return $field_types[$name]['field_type_id'];
	}

	public static function getFieldTypeNameFromId(?string $field_type_id): ?string
	{
		$field_types = self::get();

		foreach($field_types as $name => $field_type){
			if($field_type['field_type_id'] == $field_type_id){
				return $name;
			}
		}

		return NULL;
	}

	/**
	 * Returns field types as options for a dropdown list.
	 *
	 * If filters are provided, they will be applied.
	 * The key is the field_type_id, and the value is an array.
	 *
	 * Accepted filters:
	 *  - `form_field` (bool) - `true`, will only have form field, `false`, will exclude form field
	 *  - `form_value` (bool) - `true`, will only have form value, `false`, will exclude form value
	 *  - `display_only` (bool) - `true`, will only have display, `false`, will exclude display
	 *  - `exclude` (array) - field types to exclude by name or title
	 *  - `include` (array) - field types to include by name or title
	 *
	 *
	 * @param array|null $filters
	 *
	 * @return array|null
	 * @throws \Exception
	 */
	public static function getFieldTypeOptions(?array $filters = []): ?array
	{
		foreach(self::get($filters) as $field_type){
			$field_type['tooltip'] = $field_type['desc'];
			$field_type['alt'] = $field_type['desc'];

			$options[$field_type['field_type_id']] = $field_type;
		}

		return $options;
	}


	/**
	 * Returns field types.
	 * If filters are provided, they will be applied.
	 * The key is the name, and the value is an array.
	 *
	 * Accepted filters:
	 *  - `form_field` (bool) - `true`, will only have form field, `false`, will exclude form field
	 *  - `form_value` (bool) - `true`, will only have form value, `false`, will exclude form value
	 *  - `display_only` (bool) - `true`, will only have display, `false`, will exclude display
	 *  - `exclude` (array) - field types to exclude by name or title
	 *  - `include` (array) - field types to include by name or title
	 *
	 *
	 * @param array|null $filters
	 *
	 * @return array The key is the name, and the value is an array. The array will be empty if the filters are too aggressive.
	 * @throws \Exception
	 */
	public static function get(?array $filters = []): array
	{
		if(is_array($filters)){
			extract($filters);
		}

		$field_types = [];

		foreach(Info::getInstance()->getInfo("field_type") as $field_type){
			# Filter by display only
			if(key_exists("display_only", $filters)){
				if($display_only && !$field_type['settings']['display_only']){
					continue;
				}

				if(!$display_only && $field_type['settings']['display_only']){
					continue;
				}
			}

			# Filter by form fields
			if(key_exists("form_field", $filters)){
				if($form_field && !$field_type['settings']['form_field']){
					continue;
				}

				if(!$form_field && $field_type['settings']['form_field']){
					continue;
				}
			}

			# Filter by form values
			if(key_exists("form_value", $filters)){
				if($form_value && !$field_type['settings']['form_value']){
					continue;
				}

				if(!$form_value && $field_type['settings']['form_value']){
					continue;
				}
			}

			# Exclude some
			if(is_array($exclude)){
				if(str::in_array_ci($field_type['title'], $exclude)
					|| str::in_array_ci($field_type['name'], $exclude)){
					continue;
				}
			}

			# Include some
			if(is_array($include)){
				if(!str::in_array_ci($field_type['title'], $include)
					&& !str::in_array_ci($field_type['name'], $include)){
					continue;
				}
			}

			$field_types[$field_type['name']] = $field_type;
		}

		str::multidimensionalOrderBy($field_types, "order");

		return $field_types;
	}
}