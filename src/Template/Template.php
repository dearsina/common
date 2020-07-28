<?php


namespace App\Common\Template;

use App\Common\str;
use App\UI\EmailMessage;

/**
 * Class Template.
 *
 * Acts both as the Template factory, and the root class for templates.
 *
 * @package App\Common\Template
 */
class Template{
	/**
	 * The template class
	 *
	 * @var TemplateInterface
	 */
	protected object $template;

	/**
	 * Optional variables to feed the template.
	 *
	 * @var array|null
	 */
	protected array $variables = [];

	/**
	 * @param string $template_name
	 */
	public function setTemplate(string $template_name): void
	{
		# Get the template class name
		$template_class_name = str::getClassCase($template_name);

		# Find template class (first in App, then in Common)
		if(!$template_class = str::findClass($template_class_name, "Template")){
			throw new \Exception("Cannot find the {$template_class} template.");
		}

		# Store the template as an object
		$this->template = new $template_class($this->getVariables());
	}

	/**
	 * The (optional) variables to be used by the template.
	 *
	 * @return array
	 */
	public function getVariables(): ?array
	{
		return $this->variables;
	}

	/**
	 * Sets the variables needed for the template.
	 * Is fired on class construction.
	 *
	 * @param array|null $variables
	 */
	public function setVariables(?array $variables): void
	{
		if(!$variables){
			return;
		}
		$this->variables = $variables;
	}

	public function __construct(?array $variables = NULL)
	{
		$this->setVariables($variables);
	}

	/**
	 * Get the subject from the template and return it.
	 *
	 * @return string
	 */
	public function generateSubject(): string
	{
		return $this->template->getSubject();
	}

	/**
	 * Gets the message body array from the template and return it.
	 *
	 * @return array
	 */
	public function generateBody(): array
	{
		return $this->template->getBody();
	}

	/**
	 *
	 * This method should be replaced in a \App\Template\Template class,
	 * allowing rich top and tail methods.
	 *
	 * @return string
	 */
	public function generateMessage(): string
	{
		if(!$body = $this->generateBody()){
			//if there is no message body to return
			throw new \Exception("No message generated from the template.");
		}

		$email_message = new EmailMessage([
			"preheader" => $this->generatePreheader(),
			"body" => $body,
		]);

		return $email_message->getHTML();
	}

	/**
	 * Gets the template preheader.
	 *
	 * @return string
	 */
	public function generatePreheader(): ?string
	{
		return $this->template->getPreheader();
	}

	/**
	 * Returns the domain, suffixed with /.
	 * To be used as the basis for the URL for any (internal) link:
	 * <code>
	 * $url = $this->getDomain();
	 * </code>
	 *
	 * When a template is generated via CLI, the server HTTP HOST value is not populated.
	 *
	 * @return string
	 */
	public function getDomain(): string
	{
		if($_SERVER['HTTP_HOST']){
			$url = "https://{$_SERVER['HTTP_HOST']}/";
		} else {
			$url = "https://{$_ENV['app_subdomain']}.{$_ENV['domain']}/";
		}

		return $url;
	}
}