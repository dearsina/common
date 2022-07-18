<?php

namespace App\Common\Output;

use App\Common\Output;
use App\UI\Tab\Tabs;

class Tab {
	private Output $output;

	public function __construct(Output &$output)
	{
		$this->output = $output;
	}

	/**
	 * Given a tabs ID, append a new tab to the tabs.
	 *
	 * @param string     $id         The ID of the tabs where you want to append a new tab.
	 * @param array|null $tab        The tab data
	 * @param array|null $recipients If set, will perform this action asynchronously to the given recipients
	 * @param bool|null  $first		 Should this append go first in the order of events?
	 *
	 * @throws \App\Common\Exception\BadRequest
	 */
	public function append(string $id, ?array $tab = NULL, ?array $recipients = NULL, ?bool $first = NULL): void
	{
		if(!$tab){
			return;
		}

		[$header, $pane] = Tabs::generateTab($tab);

		# Switch IDs around so that the tab_id is the ID of the tab, and the id, is the ID of the parent
		$tab['tab_id'] = $tab['id'];
		$tab['id'] = $id;

		$tab['header'] = $header;
		$tab['pane'] = $pane;

		$this->output->function("appendTab", $tab, $recipients, $first);
	}

	/**
	 * Given a tabs ID, prepend a new tab to the tabs.
	 *
	 * @param string     $id         The ID of the tabs where you want to prepend a new tab.
	 * @param array|null $tab        The tab data
	 * @param bool|null  $active     If set, will set the new tab as the active tab.
	 * @param array|null $recipients If set, will perform this action asynchronously to the given recipients
	 */
	public function prepend(string $id, ?array $tab = NULL, ?bool $active = false, ?array $recipients = NULL): void
	{
		if(!$tab){
			return;
		}

		[$header, $pane] = Tabs::generateTab($tab);

		# Switch IDs around so that the tab_id is the ID of the tab, and the id, is the ID of the parent
		$tab['tab_id'] = $tab['id'];
		$tab['id'] = $id;

		$tab['header'] = $header;
		$tab['pane'] = $pane;

		$this->output->function("prependTab", $tab, $recipients);
	}

	/**
	 * Given a tab ID, will remove it.
	 *
	 * @param string|null $id
	 * @param array|null  $recipients
	 */
	public function remove(?string $id, ?array $recipients = NULL): void
	{
		if(!$id){
			return;
		}

		$this->output->function("removeTab", $id, $recipients);
	}

	/**
	 * Given a tab ID and tab data, will replace the tab with the new tab.
	 *
	 * @param string     $tab_id     The ID of the tab being updated.
	 * @param array|null $tab        The tab data.
	 * @param bool|null  $active     If set, will set the updated tab as the active tab.
	 * @param array|null $recipients If set, will perform this action asynchronously to the given recipients
	 */
	public function update(string $tab_id, ?array $tab = NULL, ?array $recipients = NULL): void
	{
		if(!$tab){
			return;
		}

		[$header, $pane] = Tabs::generateTab($tab);

		$tab['tab_id'] = $tab_id;

		$tab['header'] = $header;
		$tab['pane'] = $pane;

		$this->output->function("updateTab", $tab, $recipients);
	}

	/**
	 * Given a tab ID, will update the header (only).
	 *
	 * @param string            $id         The ID of the tab whose header is to be updated.
	 * @param string|array|null $header     The header data.
	 * @param bool|null         $active     If set, will set the updated tab as the active tab.
	 * @param array|null        $recipients If set, will perform this action asynchronously to the given recipients
	 */
	public function updateHeader(string $id, $header, ?bool $active = false, ?array $recipients = NULL): void
	{
		if(!$header){
			return;
		}

		if(!is_array($header)){
			$header = [
				"header" => $header,
			];
		}

		# Give the header the given ID
		$header['id'] = $id;

		$data = [
			"id" => $id,
			"header" => Tabs::generateHeaderHTML($header),
			"active" => $active,
		];

		$this->output->function("updateTab", $data, $recipients);
	}

	/**
	 * Given a tab ID, will update the pane (only).
	 *
	 * @param string            $id         The ID of the tab whose pane is to be updated.
	 * @param string|array|null $pane       The pane data.
	 * @param bool|null         $active     If set, will set the updated tab as the active tab.
	 * @param array|null        $recipients If set, will perform this action asynchronously to the given recipients
	 */
	public function updatePane(string $id, $pane, ?bool $active = false, ?array $recipients = NULL): void
	{
		if(!$pane){
			return;
		}

		if(!is_array($pane)){
			$pane = [
				"body" => $pane,
			];
		}

		# Give the pane the given ID
		$pane['id'] = $id;

		$data = [
			"id" => $id,
			"pane" => Tabs::generatePaneHTML($pane),
			"active" => $active,
		];

		$this->output->function("updateTab", $data, $recipients);
	}
}