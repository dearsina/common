<?php


namespace App\Common\Signature;


use App\Common\Output;
use App\Common\str;
use App\UI\Icon;
use App\UI\Modal\Modal;

class Signature extends \App\Common\Common {
	public function new(array $a): bool
	{
		extract($a);

		$html = <<<EOF
<div class="signature-pad-narrative">Please sign here with your mouse or finger</div>
<canvas></canvas>
EOF;

		$modal = new Modal([
			# How to trigger the signature
			"parent_class" => "signature-pad",

			# Where to store the signature ID, that will be populated on save
			"id" => $vars['signature_id'],

			"size" => "xl",
			"icon" => "signature",
			"header" => "Please sign here",
			"body" => [
				"html" => $html,
			],
			"footer" => [
				"button" => [[
					"class" => "signature-pad-save",
					"title" => "Save",
					"icon" => Icon::get("save"),
					"colour" => "primary",
					"ladda" => false
				],[
					"class" => "signature-pad-clear",
					"title" => "Clear",
					"icon" => Icon::get("eraser"),
					"basic" => true,
					"ladda" => false
				],"cancel_md"]
			],
			"dismissible" => false,
			"draggable" => false,
			"resizable" => false,
		]);

		$this->output->modal($modal->getHTML());

		$this->hash->set(-1);
		$this->hash->silent();

		return true;
	}
}