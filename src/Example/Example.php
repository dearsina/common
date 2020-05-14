<?php


namespace App\Common\Example;


use App\Common\Common;
use App\Common\str;
use App\UI\Page;

class Example extends Common {
	public function view($a){
		extract($a);
		$class_path = str::getClassCase("\\App\\UI\\Examples\\{$rel_id}");
		$class_instance = new $class_path();
		$class_instance->getHTML($a);

		return true;
	}
}