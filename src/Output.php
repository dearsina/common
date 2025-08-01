<?php

namespace App\Common;

use App\Common\Exception\BadRequest;
use App\Common\Output\Tab;
use App\UI\Button;
use App\UI\Form\SelectOption;

/**
 * Tracks the output of a ajax call
 */
class Output {
	private ?array $output = [];
	private array $direction = [];

	/**
	 * @var Log
	 */
	protected Log $log;

	/**
	 * @var Tab
	 */
	public Tab $tab;

	protected function __construct()
	{
		$this->log = Log::getInstance();
		$this->tab = new Tab($this);
	}

	private function __clone()
	{
		// Stopping cloning of object
	}

	private function __wakeup()
	{
		// Stopping unserialize of object
	}

	/**
	 * Used instead of new to ensure that the same instance is used every time it's initiated.
	 *
	 * @return Output
	 * @link http://stackoverflow.com/questions/3126130/extending-singletons-in-php
	 */
	final public static function getInstance(): Output
	{
		static $instance = NULL;
		if(!$instance){
			$instance = new Output();
		}
		return $instance;
	}

	/**
	 * @return bool
	 */
	public function uri()
	{
		$this->output['uri'] = true;
		return true;
	}

	/**
	 * @return array
	 */
	public function get()
	{
		return $this->output;
	}

	/**
	 * Set the entire output array at once.
	 * Only really used by the ConnectionMessage class.
	 *
	 * @param array $data
	 */
	public function set(?array $data): void
	{
		$this->output = $data;
	}

	/**
	 * Clears away any stored data for output.
	 *
	 * @return void
	 */
	public function clear(): void
	{
		$this->output = [];
	}

	/**
	 * Identifies which div to send the HTML output to,
	 * and what to do with the HTML currently in the div (replace, append, prepend, replace div).
	 *
	 * If the generic outcome is to be directed to a certain div,
	 * and either replace contents (inner), prepend, append or wholesale replace the div,
	 * it is expected that the request comes in the form of the following
	 * variable pair: div and div_id. div describes the action and div_id the
	 * div ID with which to perform the action:
	 * <code>
	 * "div" => "inner",
	 * "div_id" => "div_id_n"
	 * </code>
	 *
	 * @param $vars
	 *
	 * @return bool
	 */
	public function set_direction($vars)
	{
		$this->direction = [
			"type" => $vars['div'],
			"id" => urldecode($vars['div_id']),
		];
		return true;
	}

	/**
	 * Will update the *contents* a given div, based on their div ID.
	 * It will not touch the div tag itself.
	 *
	 * @param string        $id         Expects an ID that jQuery will understand (prefixed with # or . etc)
	 * @param string|bool   $data		If set to false, will remove any prior div content.
	 * @param array|null    $recipients If set, will send the update asynchronously to all relevant recipients
	 * @param bool|int|null $first
	 *
	 * @return bool
	 */
	public function update(string $id, $data, ?array $recipients = NULL, $first = NULL): bool
	{
		return $this->setData("update", $id, $data, $recipients, $first);
	}

	/**
	 * If you want to update the footer buttons.
	 *
	 * @param string        $id
	 * @param array         $buttons
	 * @param array|null    $recipients
	 * @param bool|int|null $first
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function updateFooterButtons(string $id, array $buttons, ?array $recipients = NULL, $first = NULL): bool
	{
		$html = Button::generate($buttons);
		$html = <<<EOF
<div class="container">
	<div class="row">
		<div class="col-auto"></div>
		<div class="col">
			<div class="btn-float-right">
				{$html}
			</div>    		
		</div>
	</div>
</div>
EOF;

		return $this->setData("update", $id, $html, $recipients, $first);
	}

	/**
	 * Will prepend a given div with data.
	 *
	 * @param string        $id
	 * @param               $data
	 * @param array|null    $recipients If set, will send the prepend asynchronously to all relevant recipients
	 * @param bool|int|null $first
	 *
	 * @return bool
	 */
	public function prepend(string $id, $data, ?array $recipients = NULL, $first = NULL): bool
	{
		return $this->setData("prepend", $id, $data, $recipients, $first);
	}

	/**
	 * Will append a given div with data.
	 *
	 * @param string        $id
	 * @param               $data
	 * @param array|null    $recipients If set, will send the append asynchronously to all relevant recipients
	 * @param bool|int|null $first
	 *
	 * @return bool
	 */
	public function append(string $id, $data, ?array $recipients = NULL, $first = NULL): bool
	{
		return $this->setData("append", $id, $data, $recipients, $first);
	}

	/**
	 * Will insert an element after the given div.
	 *
	 * @param string        $id
	 * @param               $data
	 * @param array|null    $recipients If set, will send the after-request asynchronously to all relevant recipients
	 * @param bool|int|null $first
	 *
	 * @return bool
	 */
	public function after(string $id, $data, ?array $recipients = NULL, $first = NULL): bool
	{
		return $this->setData("after", $id, $data, $recipients, $first);
	}

	/**
	 * Will replace a given div, including the div tag itself.
	 *
	 * @param string        $id
	 * @param               $data
	 * @param array|null    $recipients If set, will send the replacement asynchronously to all relevant recipients
	 * @param bool|int|null $first
	 *
	 * @return bool
	 */
	public function replace(string $id, $data, ?array $recipients = NULL, $first = NULL): bool
	{
		return $this->setData("replace", $id, $data, $recipients, $first);
	}

	/**
	 * Will remove a given div, including the div tag itself.
	 *
	 * @param string        $id
	 * @param array|null    $recipients If set, will send the remove asynchronously to all relevant recipients
	 * @param bool|int|null $first
	 *
	 * @return bool
	 */
	public function remove(string $id, ?array $recipients = NULL, $first = NULL): bool
	{
		return $this->setData("remove", $id, NULL, $recipients, $first);
	}

	/**
	 * Will execute a given function name, with the data as the variables.
	 * If `$data` is an array, will json_encode.
	 * In app.js, json_encoded arrays will automatically be decoded.
	 *
	 * Functions are treated a little different in as the data is not appended,
	 * a new instance of the function method is called.
	 *
	 * @param string        $function_name
	 * @param mixed         $data
	 * @param array|null    $recipients If set, will send the function asynchronously to all relevant recipients
	 * @param bool|int|null $first
	 *
	 * @return bool
	 */
	public function function(string $function_name, $data = NULL, ?array $recipients = NULL, $first = NULL): bool
	{
		if(is_array($data)){
			$data = json_encode($data);
		}

		$this->setData("function", 0, [$function_name => $data], $recipients, $first);

		return true;
	}

	/**
	 * Expects file data, or an array with:
	 *  - $filename
	 *  - $content_type
	 *  - $data
	 *
	 * @param mixed $a
	 *
	 * @throws BadRequest
	 */
	public function save($a, ?array $recipients = NULL): void
	{
		if(!is_array($a)){
			$a = ["data" => $a];
		}

		extract($a);

		# Filename
		$this->setVar("filename", $filename);
		# Content type
		$this->setVar("type", $content_type);

		if($data){
			# The content itself
			$this->setVar("save", base64_encode($data));
		}

		else if($url){
			$this->setVar("save", true);
			$this->setVar("url", $url);
		}

		else {
			throw new BadRequest("Either a URL or data contents must be passed to save.");
		}

		if($recipients){
			PA::getInstance()->speak($recipients, array_merge(["success" => true], $this->output));
		}
	}

	/**
	 * Appends a modal HTML string to the #ui-modal.
	 * Unless directed otherwise, modals are always created *first*
	 *
	 * @param string        $html
	 * @param array|null    $recipients
	 * @param bool|int|null $first
	 * @param string|null   $replace If modal is to replace another with the same name.
	 */
	public function modal(string $html, ?array $recipients = NULL, $first = true, ?string $replace = NULL): void
	{
		$data = [
			"id" => "#ui-modal",
			"html" => $html,
			"replace" => $replace,
		];

		$this->setData("modal", NULL, $data, $recipients, $first);
	}

	/**
	 * Opens (prepends) a window onto the #ui-modal.
	 * Now 100% sure how it works if the ui-modal is also
	 * updated in the same call.
	 *
	 * @param string        $html
	 * @param array|null    $recipients
	 * @param bool|int|null $first
	 *
	 * @return bool
	 */
	public function window(string $html, ?array $recipients = NULL, $first = NULL): bool
	{
		return $this->setData("prepend", "#ui-modal", $html, $recipients, $first);
	}

	/**
	 * Close the top-most modal.
	 * Or include a particular modal ID to close.
	 *
	 * @param string|null   $modal_id You don't need to prefix the ID with #
	 * @param array|null    $recipients
	 * @param bool|int|null $first
	 *
	 * @return mixed
	 */
	public function closeModal(?string $modal_id = NULL, ?array $recipients = NULL, $first = NULL): void
	{
		$data = [
			"id" => $modal_id,
			"close" => true,
			//			"backtrace" => $modal_id ? NULL : debug_backtrace(),
		];

		$this->setData("modal", NULL, $data, $recipients, $first);
	}

	/**
	 * Will replace the entire page (#ui-view).
	 * Or if a direction is given, will do [type] to [id].
	 *
	 * If the output is_modal is set to true,
	 * will force the output out as a modal.
	 *
	 * By default, it is placed first.
	 *
	 * @param null          $data
	 * @param bool|int|null $first
	 *
	 * @return bool
	 */
	public function html($data, $first = true)
	{
		return $this->setData("update", "#ui-view", $data, NULL, $first);
		//Updates to ui-view are by default always set first
	}

	/**
	 * Will replace the navigation elements (#ui-navigation).
	 *
	 * Doesn't go thru the set-data method because at times,
	 * by design, it will need to send a blank value back to JS.
	 *
	 * @param string|bool $data Expects HTML.
	 *
	 * @return bool
	 */
	public function navigation(?string $data)
	{
		$this->setData("update", "#ui-navigation", $data);
		return true;
	}

	/**
	 * Will replace the footer (#ui-footer).
	 *
	 * Doesn't go thru the set-data method because at times,
	 * by design, it will need to send a blank value back to JS.
	 *
	 * @param string|bool $data Expects HTML.
	 *
	 * @return bool
	 */
	public function footer(?string $data)
	{
		$this->setData("update", "#ui-footer", $data);
		return true;
	}

	/**
	 * If called, will perform ajax calls, but not update the hash.
	 * This prevents screen refresh.
	 *
	 * @param bool $true_or_false
	 *
	 * @return bool
	 */
	public function silent($true_or_false = true)
	{
		return $this->setData("silent", NULL, $true_or_false);
	}

	/**
	 * Set the title of the page.
	 *
	 * @param $page_title
	 *
	 * @return bool
	 */
	public function pageTitle($page_title)
	{
		return $this->setData("page_title", NULL, $page_title);
	}

	/**
	 * Set a custom type of variable and its value to be feed back to the browser.
	 *
	 * @param $type
	 * @param $data
	 *
	 * @return mixed
	 */
	public function setVar($type, $data)
	{
		return $this->output[$type] = $data;
	}

	/**
	 * This is for parent/child ajax calls,
	 * and for async dropdown option population.
	 *
	 * @param array|null  $rows
	 * @param string|null $placeholder
	 * @param string|null $element
	 * @param string|null $key
	 *
	 * @return void
	 */
	public function setOptions(?array $rows, ?string $placeholder = "", ?string $element = NULL, ?string $key = "results"): void
	{
		$this->output["placeholder"] = $placeholder;
		$this->output["element"] = $element;

		if($rows === NULL){
			$this->output[$key] = NULL;
			return;
		}

		$options = [];

		# If a numeric options array is passed
		if(str::isNumericArray($rows)){
			foreach($rows as $id => $row){
				# If even the value is an array of data
				if(is_array($row)){
					# Load the option
					$row['value'] = $id;
					$option = SelectOption::getFormattedOption($row);

					# And the text is replaced with the title, unless a text has been provided
					$option['text'] = $option['text'] ?: $row['title'];

					# And the ID is the same as the text
					$option['id'] = $option['text'];

					$options[] = $option;
				}

				# If the value is a string
				else {
					$options[] = [
						"id" => $row,
						"text" => $row,
					];
				}

				$options[] = [
					"id" => $row['Field'],
					"text" => $row['Field'],
				];
			}
		}

		# If the keys matter
		else {
			foreach($rows as $id => $text){
				# If even the value is an array of data (most common)
				if(is_array($text)){
					# Load the option
					$text['value'] = $id;
					$option = SelectOption::getFormattedOption($text);

					# And the text is replaced with the title, unless a text has been provided
					$option['text'] = $option['text'] ?: $option['title'];

					# Add the ID, which will be taken from the key
					$option['id'] = $id;

					$options[] = $option;
				}

				else {
					$options[] = [
						"id" => $id,
						"text" => $text,
					];
				}
			}
		}

		usort($options, function($a, $b){
			return $a['text'] <=> $b['text'];
		});

		$this->output[$key] = $options;
	}

	/**
	 *
	 * @param string        $type The name of the data key, an instruction on what to do with the data
	 * @param string|null   $id   The div ID where the data is going
	 * @param mixed         $data The HTML or instructions.
	 * @param array|null    $recipients
	 * @param bool|int|null $first
	 *
	 * @return bool
	 */
	public function setData(string $type, ?string $id, $data, ?array $recipients = NULL, $first = NULL)
	{
		if(in_array($type, ["modal", "function", "remove", "replace", "update", "prepend", "append", "after"])){
			$this->setAction($type, $id, $data, $recipients, $first);
			return true;
		}

		if($recipients){
			return PA::getInstance()->speak($recipients, [
				"success" => true,
				$type => [
					$id => $data,
				],
			]);
		}

		if($data === false){
			//if data has by design been set as false

			# Remove the existing data
			if($id){
				unset($this->output[$type][$id]);
			}
			else {
				unset($this->output[$type]);
			}
		}
		else {
			//If data has been submitted

			# Modal can either be "close" or an array of modal data
			if(is_string($this->output[$type])){
				// If it's set to "close"
				unset($this->output[$type]);
				//Remove it
			}

			# Data is *appended* to the array, NOT replaced
			if($id){
				$this->output[$type][$id] .= $data;

				# If the element is to be moved up to the top so that it's updated first
				if($first){
					$order = $first === true ? 0 : $first;
					str::repositionArrayElement($this->output[$type], $id, $order);
				}
			}
			else {
				$this->output[$type] .= $data;
			}
		}

		return true;
	}

	/**
	 * Some output types need to be processed in a particular order.
	 * This way, the order it was sent to the output array will be
	 * the order the type is processed.
	 *
	 * @param string        $type
	 * @param string|null   $id
	 * @param               $data
	 * @param array|null    $recipients
	 * @param bool|int|null $first
	 */
	private function setAction(string $type, ?string $id, $data, ?array $recipients = NULL, $first = NULL): void
	{
		if($recipients){
			PA::getInstance()->speak($recipients, [
				"success" => true,
				"silent" => $type == "remove",
				// If the action is remove, do it silently (don't mess with the URL)
				"actions" => [[
					$type => [
						$id => $data,
					],
				]],
			]);
			return;
		}

		# The default order of the action is last
		$order = count($this->output['actions'] ?: []);

		# Unless we're appending to an existing type-id
		if($id && $order){
			foreach($this->output['actions'] as $i => $action){
				if($action[$type][$id]){
					$order = $i;
					break;
				}
			}
		}

		# If data has been set to false by design
		if($data === false){
			# Remove the existing data
			if($id){
				// With an ID
				unset($this->output['actions'][$order][$type][$id]);
			}

			else {
				// Without an ID
				unset($this->output['actions'][$order][$type]);
			}

			return;
		}

		# Modal can either be "close" or an array of modal data
		if(is_string($this->output['actions'][$order][$type])){
			// If it's set to "close"
			unset($this->output['actions'][$order][$type]);
			//Remove it
		}

		# Data is *appended* to the array, NOT replaced
		if($id){
			if(!is_array($data) && !is_null($data)){
				// Arrays and nulls are not appended as strings
				$this->output['actions'][$order][$type][$id] .= $data;
			}

			else if($data){
				$this->output['actions'][$order][$type][$id][] = $data;
			}

			# Some types ("remove") don't have any data attached to the ID
			else {
				// This is also where NULL values go, and essentially clear the data
				$this->output['actions'][$order][$type][$id] = $data;
			}
		}

		else {
			if(is_string($data)){
				$this->output['actions'][$order][$type] .= $data;
			}

			else {
				$this->output['actions'][$order][$type][] = $data;
			}
		}

		# If the element is to be moved up to the top so that it's actioned first
		if($first){
			$new_order = $first === true ? 0 : $first;
			str::repositionArrayElement($this->output['actions'], $order, $new_order);
		}
	}
}