<?php

namespace App\Common\SystemMailbox;

use App\Common\OAuth2\OAuth2Handler;
use App\Common\Prototype;
use App\Common\str;
use App\UI\Button;
use App\UI\Table;

class SystemMailbox extends Prototype\ModalPrototype {
	const REL_DB = "correspondence";
	public ?string $db = "correspondence";
	/**
	 * @return Card
	 */
	public function card()
	{
		return new Card();
	}

	/**
	 * @return Modal
	 */
	public function modal()
	{
		return new Modal();
	}


	/**
	 * @return Tab
	 */
	public function tab()
	{
		return new Tab();
	}

	public function updateRelTable(array $a): void
	{
		extract($a);

		if(!$this->permission()->get($rel_table)){
			return;
		}

		$id = "#modal-system-mailbox-all .modal-body";

		if(!$system_mailboxes = $this->info($rel_table, NULL, true)){
			$this->output->update($id, "<smallest><span class=\"text-muted text-italic\">No system mailboxes found.</span></smallest>");
			return;
		}

		foreach($system_mailboxes as $system_mailbox){
			$rows[] = $this->rowHandler($system_mailbox, $a);
		}

		$this->output->update($id, Table::generate($rows));
	}
	public function rowHandler(array $cols, ?array $a = []): array
	{
		extract($a);

		$row = [];

		$row['Provider'] = [
			"html" => str::title($cols['oauth_token']['provider']),
		];

		$buttons[] = Button::generic(["remove"], $rel_table, $cols["{$rel_table}_id"]);

		$row['Email'] = [
			"title" => $cols['email'],
			"body" => $cols['desc'],
			"button" => $buttons
		];

		return $row;
	}

	public function insert(array $a): bool
	{
		extract($a);

		if(!$this->permission()->get($rel_table)){
			return $this->accessDenied();
		}

		if ($vars['provider'] == "smtp") {
			return $this->newSmtp($a);
		}

		$oauth2_handler = new OAuth2Handler();

		if(!$vars['oauth_token_id']){
			$oauth2_handler->initiateRequest($a);
			return true;
		}

		$rel_id = $this->sql->insert([
			"db" => "correspondence",
			"table" => "system_mailbox",
			"set" => [
				"oauth_token_id" => $vars['oauth_token_id'],
				"provider" => $vars['provider'],
			],
		]);

		$system_mailbox = $this->info($rel_table, $rel_id);

		$class = OAuth2Handler::getProviderClass($system_mailbox['oauth_token']['provider']);
		$provider = new $class($system_mailbox['oauth_token']);

		$this->sql->update([
			"db" => "correspondence",
			"table" => "system_mailbox",
			"set" => [
				"email" => $provider->getEmailAddress(),
			],
			"id" => $rel_id,
		]);

		# Close the OAuth2 popup window
		$oauth2_handler->closeWindow();

		# Update the email table
		$this->updateRelTable($a);

		# Close the provider modal
		$this->output->closeModal();

		return true;
	}

	private function newSmtp(array $a): bool
	{
		extract($a);

		if(!$this->permission()->get($rel_table)){
			return $this->accessDenied();
		}

		$this->output->modal($this->modal()->newSmtp($a));

		# Return the URL to what it was
		$this->hash->set(-1);

		# Don't refresh the whole page
		$this->hash->silent();

		return true;
	}
}