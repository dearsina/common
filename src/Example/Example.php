<?php


namespace App\Common\Example;


use App\Common\Common;
use App\Common\str;
use App\UI\Button;
use App\UI\Card;
use App\UI\Icon;
use App\UI\ListGroup;
use App\UI\Page;

/**
 * Class Example
 * @package App\Common\Example
 */
class Example extends Common {
	/**
	 * @param $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function view($a){
		extract($a);

		if($rel_id){
			$page = new Page([
				"title" => str::title($rel_id),
				"icon" => Icon::get($rel_id),
				"button" => [
					"icon" => "chevron-left",
					"title" => "Return",
					"basic" => true,
					"hash" => -1,
					"style" => [
						"margin-bottom" => "1rem"
					]
				]
			]);

//			$page->setGrid([
//
//			]);

			$this->output->html($page->getHTML());

			$class_path = str::getClassCase("\\App\\UI\\Examples\\{$rel_id}");
			$class_instance = new $class_path();
			$class_instance->getHTML($a);
			return true;
		}

		$page = new Page([
			"title" => "Examples",
			"subtitle" => "Examples of UI elements in use",
			"icon" => Icon::get("example"),
		]);

		foreach(array_diff(scandir("/var/www/vendor/dearsina/ui/examples"),['.', '..']) as $file){
			$item = explode(".",$file)[0];
			$items[] = [
				"icon"=> Icon::get($item),
				"html" => $item,
				"hash" => [
					"rel_table" => $rel_table,
					"rel_id" => $item
				]
			];
		}

		$card = new Card([
			"body" => ListGroup::generate([
				"flush" => true,
				"items" => $items
			])
		]);

		$page->setGrid([[
			"sm" => 3,
			"html" => $card->getHTML()
		]]);

		$this->output->html($page->getHTML());
		return true;
	}
}