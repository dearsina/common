<?php


namespace App\Common\Email;


use App\Common\Common;
use App\Common\str;
use App\UI\Grid;

class TemplateConstructor extends Common {
	/**
	 * An array with all the variables to be used
	 * in the template.
	 * 
	 * @var array
	 */
	protected $variables;
	public function __construct ($a) {
		$this->variables = $a;
	}

	/**
	 * Will return the entire message body
	 * as a HTML string. Wil include
	 * app wide (optional) headers and footers.
	 *
	 * @return bool|string
	 */
	public function getMessageHTML(){
		if(!$body = $this->getMessage()){
			return false;
		}
		return <<<EOF
{$this->getHeader()}
{$body}
{$this->getFooter()}
EOF;
	}

	private function getHeader(){
		return <<<EOF

EOF;
	}

	private function getFooter(){
		return <<<EOF

EOF;
	}
}