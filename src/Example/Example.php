<?php


namespace App\Common\Example;


use App\Common\Common;
use App\Common\str;
use App\UI\Button;
use App\UI\Card;
use App\UI\Icon;
use App\UI\ListGroup;
use App\UI\Page;

class Example extends Common {
	public function view($a){
		extract($a);

		if($rel_id){
			$page = new Page([
				"title" => str::title($rel_id),
				"icon" => Icon::get($rel_id),
			]);
			$page->setGrid([
				"html" => Button::generate("return"),
				"style" => [
					"margin-bottom" => "1rem"
				]
			]);
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

		$page->setGrid($card->getHTML());

		$this->output->html($page->getHTML());
		return true;
	}
}