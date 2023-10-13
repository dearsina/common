<?php


namespace App\Common\Role;


/**
 * Class Field
 * @package App\Common\Role
 */
class Field {
	public static function role(?array $a = NULL) : array
	{
		if (is_array($a))
			extract($a);

		return [[
			"name" => "role",
			"value" => $role,
			"label" => false,
			"validation" => [
				"regex" => [
					"rule" => "^[a-z_]+\$",
					"msg" => "Only lowercase a-z and _ are allowed."
				],
				"required" => [
					"rule" => true,
					"msg" => "This field is required."
				]
			],
			"desc" => "Only lowercase a-z and _ are allowed."
		],[
			"name" => "icon",
			"value" => $icon,
			"label" => false,
			"desc" => "A valid Font Awesome icon name.",
			"required" => true
		]];
	}
}