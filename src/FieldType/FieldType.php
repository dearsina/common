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
	 * @param string|null $field_type_id
	 *
	 * @return bool
	 */
	public static function isDisplayOnly(?string $field_type_id): bool
	{
		if(!$field_type_id){
			return false;
		}

		return (bool) FieldType::getDisplayOnlyFieldTypes()[$field_type_id];
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
				"display_only" => 1
			]
		]) ?:[] as $field_type){
			$display_only[$field_type['field_type_id']] = $field_type['name'];
		}

		return $display_only ?:[];
	}
}