<?php


namespace App\Common\FieldType;


use App\Common\SQL\Info\Info;
use App\Common\str;
use App\UI\Badge;

class FieldType extends \App\Common\Prototype\ModalPrototype {
	/**
	 * @return Card
	 */
	public function card(){
	    return new Card();
	}

	/**
	 * @return Modal
	 */
	public function modal(){
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
				"margin" => "0.3rem 0"
			],
			"icon" => $cols['icon'],
		];

	    if($cols['display_only']){
			$badges[] = [
	    		"icon" => "eye",
				"colour" => "primary",
				"basic" => true,
				"alt" => "Display only"
			];
		}

	    $row['Type'] = [
	    	"accordion" => [
				"header" => [
					"title" => $cols['title'],
					"class" => "text-title",
					"badge" => $badges
				],
				"body" => [
					"html" => $cols['desc'],
					"class" => "text-body"
				]
			],
		];

	    $row['HTML'] = [
	    	"html" => $cols['name'],
			"sm" => 2
		];

	    $row[''] = [
			"sortable" => false,
	    	"button" => $this->getRowButtons($cols, $a),
			"sm" => 2
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
		$display_only_fields = FieldType::getDisplayOnlyFieldTypes();

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

	/**
	 * Get a list of fields that are marked as display only.
	 * The key is the field_type_id, and the value is the field
	 * type name.
	 *
	 * @return array
	 * @throws \Exception
	 */
	public static function getDisplayOnlyFieldTypes(): array
	{
		foreach((Info::getInstance())->getInfo([
			"columns" => [
				"field_type_id",
				"name"
			],
			"rel_table" => "field_type",
			"where" => [
				"display_only" => 1,
			]
		]) ?:[] as $field_type){
			$display_only[$field_type['field_type_id']] = $field_type['name'];
		}

		return $display_only ?:[];
	}
}