<?php


namespace App\Common\CronJob;

use App\Common\Common;
use App\Common\Process;
use App\Common\Request;
use App\Common\str;
use App\UI\Badge;
use App\UI\Icon;
use App\UI\Page;
use App\UI\Table;

/**
 * Class CronJob
 * @package App\Common\CronJob
 */
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

	/**
	 * View all Cron jobs. Will open a modal.
	 *
	 * @param array $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function all(array $a) : bool
	{
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		$page = new Page([
			"title" => "Cron jobs",
			"icon" => Icon::get("cron_job")
		]);

		$page->setGrid([
			"html" => $this->card()->all($a)
		]);

		$page->setGrid([
			"html" => $this->card()->running($a)
		]);

		$this->output->html($page->getHTML());

		# Get the latest issue types
		$this->updateRelTable($a);

		return true;
	}

	/**
	 * Edit one cron job. Will open a modal.
	 *
	 * @param array $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
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

	/**
	 * New cron job form. Will open a modal.
	 *
	 * @param array $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
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

	/**
	 * Insert a new cron job.
	 *
	 * @param array $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function insert (array $a) : bool
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

	/**
	 * Update a cron job.
	 *
	 * @param array $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function update (array $a) : bool
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

	/**
	 * Same as update, but won't close the modal.
	 *
	 * @param array $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function pause(array $a) : bool
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

		# Get the latest issue types
		$this->updateRelTable($a);

		return true;
	}

	/**
	 * Removes a cron job.
	 *
	 * @param array $a
	 * @param null  $silent
	 *
	 * @return bool
	 * @throws \Exception
	 */
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

	/**
	 * Given a class name, will return a list of all class methods.
	 * Is designed to work as a callback for whenever someone
	 * changes the value of the class dropdown.
	 *
	 * @param $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
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

	/**
	 * Reorders cron jobs.
	 * Checks credentials, then sends user off to
	 * generic setOrder method.
	 *
	 * @param $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function reorder($a) : bool
	{
		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		return $this->setOrder($a);
	}

	/**
	 * Is called by various methods to refresh
	 * the table of rows.
	 *
	 * @param $a
	 *
	 * @throws \Exception
	 */
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

				if($job['paused']){
					$buttons[] = [
						"hash" => [
							"rel_table" => $rel_table,
							"rel_id" => $job["cron_job_id"],
							"action" => "pause",
							"vars" => [
								"paused" => "false"
							]
						],
						"alt" => "Unpause and resume running this job on schedule",
						"icon" => "pause",
						"colour" => "primary",
						"size" => "xs",
					];
				} else {
					$buttons[] = [
						"hash" => [
							"rel_table" => $rel_table,
							"rel_id" => $job["cron_job_id"],
							"action" => "pause",
							"vars" => [
								"paused" => 1
							]
						],
						"alt" => "Pause this job from running on schedule",
						"icon" => "pause",
						"colour" => "primary",
						"size" => "xs",
						"basic" => true
					];
				}

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
						"header_style" => [
							"opacity" => 0
						],
						"button" => $buttons
					]
				];
			}
		} else {
			$rows[] = [
				"id" => true,
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

	public function runScheduled() : bool
	{
		if(!str::runFromCLI()){
			throw new \Exception("You can only run this method from the command line.");
		}

		# Set the user ID to be zero, the system user ID
		global $user_id;
		$user_id = "0";
		$_SESSION['user_id'] = $user_id;

		# Get all active (non-paused) cron jobs
		if(!$cron_jobs = $this->sql->select([
			"table" => "cron_job",
			"where" => [
				"paused" => NULL
			]
		])){
			//if no cron jobs are scheduled, close up shop
			return true;
		}

		// Create a new scheduler
		$scheduler = new \GO\Scheduler();

		foreach($cron_jobs as $cron_job) {
			//for each cron job scheduled
			$func = function($a){
				$request = new Request();
				return $request->handler($a);
			};
			$args = [[
				"rel_table" => end(explode("\\", $cron_job['class'])),
				"action" => $cron_job['method'],
				"cron_job" => $cron_job,
			]];

			$scheduler
			->call($func, $args, $cron_job['cron_job_id'])
			->at($cron_job['interval']) //at the intervals determined
			->before(function() {
				$this->log->clearAlerts();
				$this->log->startTimer();
			})
			->then(function($output) use ($cron_job){
				$this->sql->insert([
					"table" => "cron_log",
					"set" => [
						"cron_job_id" => $cron_job['cron_job_id'],
						"status" => $this->log->getStatus(),
						"duration" => $this->log->getDuration(),
						"output" => $output
					]
				]);
			});
		}
		$scheduler->run();
		return true;
	}

	/**
	 * @param $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function run($a){
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		if(!$cron_job = $this->sql->select([
			"table" => $rel_table,
			"id" => $rel_id
		])){
			throw new \Exception("The cron job cannot be found.");
		}

		// Create a new scheduler
		$scheduler = new \GO\Scheduler();

		$func = function($a){
//			$request = new Request();
//			return $request->handler($a);
			extract($a);

			$cron_job_json = str_replace('"','\\"',json_encode($cron_job));

			$cmd  = "go(function(){";
			$cmd .= "require \"/var/www/html/app/settings.php\";";
			$cmd .= "var_dump(\$_ENV);";
			$cmd .= "var_dump(\$_SERVER);";
			$cmd .= "\$cron_job = new \App\Common\CronJob\CronJob();";
			$cmd .= "\$cron_job->execute(\"{$cron_job_json}\");";
			$cmd .= "});";

			$json_output = shell_exec("php -r '{$cmd}' 2>&1");
			$this->log->warning($json_output);
//			$process = new Process("php -r '{$cmd}'");
//			echo $process->getPid();
//			$this->log->info($process->getPid(), true);
			return true;

			/*
			# Create a new instance of the class
			$classInstance = new $cron_job['class']($this);

			# Ensure the method is available
			if(!str::methodAvailable($classInstance, $cron_job['method'], "protected")){
				throw new \Exception("The <code>".$cron_job['method']."</code> method doesn't exist or is not protected or public.");
			}

			# Run the method
			$success = $classInstance->{$cron_job['method']}();

			# Analyse the result
			if($success === true){
				//not sure if this will have unintended consequences
				$output['success'] = true;
			} else if($this->log->hasFailures()){
				//if there are any errors
				$this->log->logFailures();
				//log them for posterity
				$output['success'] = false;
			} else if($success === false) {
				$output['success'] = false;
			} else {
				//otherwise, everything is fantastic
				$output['success'] = true;
			}

			return true;*/
		};

		$args = [[
//			"rel_table" => end(explode("\\", $cron_job['class'])),
//			"action" => $cron_job['method'],
			"cron_job" => $cron_job,
		]];

		$scheduler
			->call($func, $args, $cron_job['cron_job_id'])
			// We're ignoring the ->at() method, because we're going to run it irrespective of schedule
			->before(function() {
				$this->log->clearAlerts();
				$this->log->startTimer();
			})
			->then(function($output) use ($cron_job){
				$this->sql->insert([
					"table" => "cron_log",
					"set" => [
						"cron_job_id" => $cron_job['cron_job_id'],
						"status" => $this->log->getStatus(),
						"duration" => $this->log->getDuration(),
						"output" => $output
					]
				]);
			});

		ini_set('max_execution_time', 0);
		$scheduler->run();

		$this->hash->set(-1);

		return true;
	}

	public function execute(string $cron_job_json)
	{
		$cron_job = json_decode($cron_job_json, true);

		# Create a new instance of the class
		$classInstance = new $cron_job['class']($this);

		# Ensure the method is available
		if(!str::methodAvailable($classInstance, $cron_job['method'], "protected")){
			throw new \Exception("The <code>".$cron_job['method']."</code> method doesn't exist or is not protected or public.");
		}

		# Run the method
		$success = $classInstance->{$cron_job['method']}();

		echo "Execute is done";
		return true;
	}
}