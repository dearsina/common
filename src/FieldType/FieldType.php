<?php


namespace App\Common\FieldType;


class FieldType extends \App\Common\CommonModal {
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

	    $row['Type'] = [
	    	"accordion" => [
				"header" => [
					"title" => $cols['title'],
					"class" => "text-title",
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
}