<?php


namespace App\Common\Email;


use App\Common\Common;

/**
 * Class TemplateConstructor
 * @package App\Common\Email
 */
class TemplateConstructor extends Common implements TemplateConstructorInterface {
	/**
	 * An array with all the variables to be used
	 * in the template.
	 * @var array
	 */
	protected $variables;

	/**
	 * TemplateConstructor constructor.
	 *
	 * @param $a
	 */
	public function __construct($a)
	{
		parent::__construct();
		$this->variables = $a;
	}

	/**
	 * Will return the entire message body
	 * as a HTML string. Wil include
	 * app wide (optional) headers and footers.
	 *
	 * This method should be replaced in a \App\Email\TemplateConstructor class,
	 * allowing for variables to be passed to the header and footer methods.
	 *
	 * @return bool|string
	 */
	public function getMessageHTML()
	{
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
	 * This method should be replaced in a \App\Email\TemplateConstructor class
	 * and contain the actual HTML footer.
	 *
	 * @param array $a
	 *
	 * @return string
	 * @link https://htmlemail.io/inline
	 */
	public function getHeader(?array $a = NULL): ?string
	{
		if(is_array($a)){
			extract($a);
		}

		return NULL;
	}

	/**
	 * This method should be replaced in a \App\Email\TemplateConstructor class
	 * and contain the actual HTML footer.
	 *
	 * @param array $a
	 *
	 * @return string
	 */
	public function getFooter(?array $a = NULL): ?string
	{
		if(is_array($a)){
			extract($a);
		}

		return NULL;
	}
}