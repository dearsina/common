<?php


namespace App\Common\IssueType;


/**
 * Class Field
 * @package App\Common\IssueType
 */
class Field {
	/**
	 * @param null $a
	 *
	 * @return array[]
	 */
	public static function issueType($a = NULL){
		if(is_array($a))
			extract($a);

		return [[
			"name" => "title",
			"label" => false,
			"value" => $title,
			"placeholder" => "Issue type name",
			"required" => "An issue type needs a name.",
		],[
			"type" => "textarea",
			"name" => "desc",
			"label" => false,
			"placeholder" => "Issue type description.",
			"required" => "A short narrative describing this type is very useful.",
			"value" => $desc,
		],[
			"name" => "icon",
			"label" => false,
			"value" => $icon,
			"placeholder" => "FontAwesome icon name",
			"required" => "An issue type needs an icon.",
		]];
	}
}