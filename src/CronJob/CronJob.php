<?php


namespace App\Common\CronJob;

use App\Common\Common;
use App\Common\str;
use App\UI\Badge;
use App\UI\Icon;
use App\UI\Table;

class CronJob extends Common {
	/**
	 * @return Card
	 */
	public function card(){
		return new Card();
	}

	/**
	 * @return Modal
	 */
	public function modal(){
		return new Modal();
	}

	public function all(array $a) : bool
	{
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		$this->output->modal($this->modal()->all($a));

		# Get the latest issue types
		$this->updateRelTable($a);

		$this->hash->set(-1);
		$this->hash->silent();

		return true;
	}

	public function edit(array $a) : bool
	{
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		$this->output->modal($this->modal()->edit($a));

		$this->hash->set(-1);
		$this->hash->silent();

		return true;
	}

	public function new(array $a) : bool
	{
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		$this->output->modal($this->modal()->new($a));

		$this->hash->set(-1);
		$this->hash->silent();

		return true;
	}

	public function insert(array $a, $silent = NULL) : bool
	{
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		$vars['order'] = $this->getOrder($rel_table);

		$this->sql->insert([
			"table" => $rel_table,
			"set" => $vars
		]);

		# Closes the (top-most) modal
		$this->output->closeModal();

		# Get the latest issue types
		$this->updateRelTable($a);

		return true;
	}

	public function update(array $a, $silent = NULL) : bool
	{
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		$this->sql->update([
			"table" => $rel_table,
			"set" => $vars,
			"id" => $rel_id
		]);

		# Closes the (top-most) modal
		$this->output->closeModal();

		# Get the latest issue types
		$this->updateRelTable($a);

		return true;
	}

	public function remove(array $a, $silent = NULL) : bool
	{
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		$this->sql->remove([
			"table" => $rel_table,
			"id" => $rel_id
		]);

		if($silent){
			return true;
		}

		# Get the latest issue types
		$this->updateRelTable($a);

		return true;
	}

	public function getClassMethods($a) : bool
	{
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		$options[] = [
			"id" => "",
			"text" => ""
		];

		if(!$methods = str::getMethodsFromClass($vars['class_name'])){
			$this->output->set_var("options", $options);
			$this->output->set_var("placeholder", "No methods exist for this class.");
			return true;
		}

		foreach($methods as $method){
			$options[] = [
				"id" => $method['name'],
				"text" => $method['name']
			];
		}

		$this->output->set_var("options", $options);
		$this->output->set_var("placeholder", "Select a method for the ".end(explode("\\",$vars['class_name']))." class.");
		return true;
	}

	public function reorder($a) : bool
	{
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		return $this->setOrder($a);
	}

	protected function updateRelTable($a) : void
	{
		extract($a);

		$cron_jobs = $this->sql->select([
			"table" => $rel_table,
			"order_by" => [
				"order" => "ASC"
			]
		]);

		if($cron_jobs){
			foreach($cron_jobs as $job){
				$buttons = [];

				$buttons[] = [
					"alt" => "Execute this job...",
					"colour" => "yellow",
					"size" => "xs",
					"basic" => true,
					"icon" => "play",
					"hash" => [
						"rel_table" => $rel_table,
						"rel_id" => $job["cron_job_id"],
						"action" => "run"
					],
					"approve" => [
						"colour" => "yellow",
						"icon" => "play",
						"title" => "Execute cron job?",
						"message" => "Start running this job? Depending on the job, this could take a while."
					]
				];

				$buttons[] = [
					"hash" => [
						"rel_table" => "cron_log",
						"action" => "all",
						"vars" => [
							"cron_job_id" => $job["cron_job_id"],
						]
					],
					"alt" => "See log",
					"icon" => Icon::get("log"),
					"colour" => "info",
					"size" => "xs",
					"basic" => true
				];
				$buttons[] = [
					"hash" => [
						"rel_table" => $rel_table,
						"rel_id" => $job["cron_job_id"],
						"action" => "remove"
					],
					"alt" => "Remove..",
					"icon" => Icon::get("trash"),
					"colour" => "danger",
					"size" => "xs",
					"basic" => true,
					"approve" => true
				];

				if($job['paused']){
					$badge = Badge::generate([
						"title" => "PAUSED",
						"colour" => "blue"
					]);
				} else {
					$badge = Badge::generate([
						"title" => $job['interval'],
						"colour" => "red"
					]);
				}

				$title = <<<EOF
<span class="text-header">{$job['title']} {$badge}</span><br/>
<span class="text-desc">{$job['desc']}</span>
EOF;

				$rows[] = [
					"order" =>  $job['order'],
					"id" => $job['cron_job_id'],
					"Cron jobs" => [
						"html" => $title,
						"hash" => [
							"rel_table" => $rel_table,
							"rel_id" => $job['cron_job_id'],
							"action" => "edit"
						]
					],
					"Action" => [
						"sortable" => false,
						"sm" => 3,
						"class" => "float-right",
						"button" => $buttons
					]
				];
			}
		} else {
			$rows[] = [
				"Cron jobs" => [
					"icon" => Icon::get("new"),
					"html" => "New cron job...",
					"hash" => [
						"rel_table" => $rel_table,
						"action" => "new"
					]
				]
			];
		}

		$this->output->update("all_cron_job", Table::generate($rows, [
			"rel_table" => $rel_table,
			"order" => true
		]));
	}
}