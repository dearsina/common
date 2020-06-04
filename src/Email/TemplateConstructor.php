<?php


namespace App\Common\Email;


use App\Common\Common;

/**
 * Class TemplateConstructor
 * @package App\Common\Email
 */
class TemplateConstructor extends Common {
	/**
	 * An array with all the variables to be used
	 * in the template.
	 * 
	 * @var array
	 */
	protected $variables;

	/**
	 * TemplateConstructor constructor.
	 *
	 * @param $a
	 */
	public function __construct ($a) {
		parent::__construct();
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

	/**
	 * @return string
	 */
	private function getHeader(){
		return <<<EOF

EOF;
	}

	/**
	 * @return string
	 */
	private function getFooter(){
		return <<<EOF

EOF;
	}
}