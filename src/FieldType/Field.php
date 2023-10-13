<?php


namespace App\Common\FieldType;


class Field {
	public static function FieldType(?array $a = NULL): array
	{
	    if(is_array($a))
	        extract($a);

	    $data = [[
			"name" => "icon",
			"desc" => "FontAwesome icon name.",
			"value" => $icon,
			"required" => true
		],[
			"name" => "title",
			"title" => "Colloquial field name",
			"value" => $title,
			"required" => true
		],[
			"name" => "desc",
			"desc" => "Short description, shared with the user.",
			"value" => $desc,
			"type" => "textarea",
			"rows" => 4,
			"required" => true
		]];

	    $size = [[
			"name" => "min_width",
			"title" => "Minimum width",
			"placeholder" => false,
			"desc" => "Whole number larger than 0.",
			"value" => $min_width,
			"required" => true
		],[
			"name" => "min_height",
			"title" => "Minimum height",
			"placeholder" => false,
			"desc" => "Whole number larger than 0.",
			"value" => $min_height,
			"required" => true
		],[
			"name" => "name",
			"title" => "Formal field name",
			"desc" => "The official name for this field type. In lowercase. This value will not be shown to the user.",
			"value" => $name,
			"required" => true
		]];

	    $checkboxes = [[
			"type" => "checkbox",
			"name" => "display_only",
			"checked" => $display_only,
			"label" => [
				"title" => "Display only",
			],
			"desc" => "Display only fields cannot be populated like a traditional form element.
			Rather they only display information. This is an important distinction if you want to separate
			out the fields that someone can populate for the sake of form completion, and those they cannot."
		],[
			"type" => "checkbox",
			"name" => "form_value",
			"checked" => $form_value,
			"label" => [
				"title" => "Form value",
			],
			"desc" => "This field type can also be a form value field type."
		]];

	    return [[[
	    	"html" => $data,
		],[
			"html" => $size,
			"sm" => 4
		]],$checkboxes];
	}
}