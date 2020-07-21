<?php


namespace App\Common\Email;


interface TemplateConstructorInterface {
	/**
	 * This is the only method is that is actually called from the \Common\Email\Email class.
	 * It expects the entire, formatted, HTML message body.
	 *
	 * Will return the entire message body
	 * as a HTML string. Wil include
	 * app wide (optional) headers and footers.
	 *
	 * This method should be replaced in a \App\Email\TemplateConstructor class,
	 * allowing for variables to be passed to the header and footer methods.
	 *
	 * @return bool|string
	 */
	public function getMessageHTML();

	/**
	 * This method should be replaced in a \App\Email\TemplateConstructor class
	 * and contain the actual HTML top.
	 *
	 * @param array $a
	 *
	 * @return string
	 * @link https://htmlemail.io/inline
	 */
	public function getHeader(?array $a = NULL);

	/**
	 * This method should be replaced in a \App\Email\TemplateConstructor class
	 * and contain the actual HTML tail.
	 *
	 * @param array $a
	 *
	 * @return string
	 */
	public function getFooter(?array $a = NULL);
}