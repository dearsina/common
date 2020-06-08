<?php

namespace App\Common;

use App\UI\Modal;

/**
 * Tracks the output of a ajax call
 */
class Output {
	private $output = [];
	private $direction = [];

	/**
	 * A boolean value, when set,
	 * means that the output needs
	 * to be in modal form.
	 *
	 * @var bool
	 */
	private $is_modal = false;

	/**
	 * Classes.
	 *
	 * @var log
	 */
	protected $log;

	protected function __construct() {
		$this->log = Log::getInstance();
	}

	private function __clone() {
		// Stopping cloning of object
	}

	private function __wakeup() {
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
		static $instance = null;
		if (!$instance) {
			$instance = new Output();
		}
		return $instance;
	}

	/**
	 * Shortcut to setting multiple outputs at once.
	 *
	 * @param array $a [$type][$id] = $data, or [$type] = $data, depending on the type.
	 *
	 * @return bool
	 */
	public function set($a){
		if(!is_array($a)){
			$this->log->error("Only arrays are accepted, otherwise use individual field methods.");
			return false;
		}
		foreach($a as $type => $ids_or_data){
			if(in_array($type, ["div", "prepend", "append", "replace"])){
				if(!is_array($ids_or_data)) {
					//div, prepend, append, and replace require an ID.
					$this->log->error("The {$type} output type requires an ID and data.");
					return false;
				}
				foreach($ids_or_data as $id => $data){
					$this->$type($id, $data);
				}
			} else if(in_array($type, ["html", "navigation", "footer", "modal", "silent", "page_title", "page_title"])) {
				//these types do not require an ID
				$this->$type($ids_or_data);
			} else {
				$this->log->error("The {$type} output type is not recognised.");
				return false;
			}
		}
		return true;
	}

	/**
	 * @return bool
	 */
	public function uri(){
		$this->output['uri'] = true;
		return true;
	}

	/**
	 * @return array
	 */
	public function get(){
		return $this->output;
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
	public function set_direction($vars){
		$this->direction = [
			"type" => $vars['div'],
			"id" => urldecode($vars['div_id'])
		];
		return true;
	}

	/**
	 * Will update the *contents* a given div, based on their div ID.
	 * It will not touch the div tag itself.
	 *
	 * @param $id
	 * @param $data
	 *
	 * @return bool
	 */
	public function update($id, $data){
		return $this->set_data("update", $id, $data);
	}

	/**
	 * Will change the value of an element, based on their ID.
	 * It will not touch the element itself.
	 * Is predominantly for form elements that have a value="" attribute.
	 *
	 * @param $id
	 * @param $data
	 *
	 * @return bool
	 */
	public function val($id, $data){
		return $this->set_data("val", $id, $data);
	}

	/**
	 * Will prepend a given div with data.
	 *
	 * @param $id
	 * @param $data
	 *
	 * @return bool
	 */
	public function prepend($id, $data){
		return $this->set_data("prepend", $id, $data);
	}

	/**
	 * Will append a given div with data.
	 * @param $id
	 * @param $data
	 *
	 * @return bool
	 */
	public function append($id, $data){
		return $this->set_data("append", $id, $data);
	}

	/**
	 * Will replace a given div, including the div tag itself.
	 *
	 * @param $id
	 * @param $data
	 *
	 * @return bool
	 */
	public function replace($id, $data){
		return $this->set_data("replace", $id, $data);
	}

	/**
	 * Output a modal. Can either be a modal array
	 * or modal HTML.
	 *
	 * <code>
	 * $this->output->modal([
	 *    //"id" => str::id("modal"),
	 *    //"size" => "xl",
	 *    "header" => "Modal header",
	 *    "body" => "Modal body",
	 *    "footer" => "Modal footer",
	 *    //"dismissible" => false,
	 *    "draggable" => true,
	 *    "resizable" => true,
	 *    "approve" => true,
	 * ]);
	 * </code>
	 *
	 * @param $a
	 * @return bool
	 */
	public function modal($a){
		if(is_array($a)){
			$modal = new Modal($a);
			return $this->set_data("append", "ui-view", $modal->getHTML());
		}

		if(is_string($a)){
			return $this->set_data("append", "ui-view", $a);
		}

		return false;
	}

	/**
	 * @return mixed
	 */
	public function closeModal(){
		return $this->set_var("modal", "close");
	}

	/**
	 * Sets the is_modal value.
	 * By default, sets it to true.
	 *
	 * @param bool $bool
	 *
	 * @return bool
	 */
	public function is_modal($bool = true){
		$this->is_modal = $bool;
		return true;
	}

	/**
	 * Will replace the entire page (#ui-view).
	 * Or if a direction is given, will do [type] to [id].
	 *
	 * If the output is_modal is set to true,
	 * will force the output out as a modal.
	 *
	 * @param null $data
	 *
	 * @return bool
	 */
	public function html($data){
//		if($this->is_modal){
//			return $this->modal($data);
//		}
//		if($this->direction){
//			return $this->set_data($this->direction["type"], $this->direction["id"], $data);
//		}
		# I don't think we need these things any more, kept for reference only

		return $this->set_data("update", "ui-view", $data);
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
	public function navigation(?string $data){
		$this->output['update']["ui-navigation"] = $data;
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
	public function footer(?string $data){
		$this->output['update']["ui-footer"] = $data;
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
	public function silent($true_or_false = TRUE){
		return $this->set_data("silent", NULL, $true_or_false);
	}

	/**
	 * Set the title of the page.
	 *
	 * @param $page_title
	 *
	 * @return bool
	 */
	public function pageTitle($page_title){
		return $this->set_data("page_title", NULL, $page_title);
	}

	/**
	 * Set a custom type of variable and its value to be feed back to the browser.
	 *
	 * @param $type
	 * @param $data
	 *
	 * @return mixed
	 */
	public function set_var($type, $data){
		return $this->output[$type] = $data;
	}

	/**
	 *
	 * @param string $type The name of the data key, an instruction on what to do with the data
	 * @param string $id The div ID where the data is going
	 * @param string $data The HTML or instructions.
	 *
	 * @return bool
	 */
	private function set_data($type, $id, $data){
		if($data === false){
			//if data has by design been set as false

			# Remove the existing data
			if($id){
				unset($this->output[$type][$id]);
			} else {
				unset($this->output[$type]);
			}
		} else {
			//If data has been submitted

			# Data is *appended* to the array, NOT replaced
			if($id){
				$this->output[$type][$id] .= $data;
			} else {
				$this->output[$type] .= $data;
			}
		}

		return true;
	}

	/**
	 * Request a certain kind of output data.
	 *
	 * @param $type
	 * @param $id
	 *
	 * @return mixed
	 */
	private function getOutput($type, $id){
		if($id){
			return $this->output[$type][$id];
		} else {
			return $this->output[$type];
		}
	}
}