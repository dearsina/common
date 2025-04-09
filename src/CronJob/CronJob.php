<?php


namespace App\Common\CronJob;

use App\Common\Prototype;
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
class CronJob extends Prototype {
	public const INTERVALS = [
		'@yearly' => 'Yearly',
		'@monthly' => 'Monthly',
		'@weekly' => 'Weekly',
		'@daily' => 'Daily, at midnight UTC',
		'0 2 * * *' => 'Daily, at 2am UTC',
		'0 4 * * *' => 'Daily, at 4am UTC',
		'@hourly' => 'Hourly',
		'0,30 * * * *' => "Every 30 minutes",
		'0/15 * * * *' => "Every 15 minutes",
		'*/5 * * * *' => "Every 5 minutes",
		'* * * * *' => "Every minute",
	];

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

		# Get the latest update of the CronJobs table
		$this->updateCronJobs($a);

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

		# Get the latest update of the CronJobs table
		$this->updateCronJobs($a);

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

		# Get the latest update of the CronJobs table
		$this->updateCronJobs($a);

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

		# Get the latest update of the CronJobs table
		$this->updateCronJobs($a);

		# Return
		$this->hash->set(-1);

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
	public function remove(array $a, ?bool $silent = NULL): bool
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

		# Get the latest update of the CronJobs table
		$this->updateCronJobs($a);

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
			"title" => ""
		];

		if(!$methods = str::getMethodsFromClass($vars['class'])){
			$this->output->setOptions( $options, "No methods exist for this class.");
			return true;
		}

		foreach($methods as $method){
			$options[] = [
				"id" => $method['name'],
				"title" => $method['name']
			];
		}
		$class = explode("\\",$vars['class']);
		$this->output->setOptions( $options, "Select a method for the ".end($class)." class.");
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

		$this->setOrder($a);

		return true;
	}

	/**
	 * Is called by various methods to refresh
	 * the table of rows of Cron Jobs.
	 *
	 * @param array $a
	 *
	 * @throws \Exception
	 */
	public function updateCronJobs(array $a) : bool
	{
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		$cron_jobs = $this->sql->select([
			"table" => $rel_table,
			"order_by" => [
				"order" => "ASC"
			]
		]);

		if($cron_jobs){
			foreach($cron_jobs as $job){
				$buttons = [];

				# Check to see if the job is still running
				if($job['pid']) {
					$process = new Process();
					$process->setPid($job['pid']);
					if (!$process->status()) {
						//if the process is no longer active
						$this->sql->update([
							"table" => "cron_job",
							"id" => $job['cron_job_id'],
							"set" => [
								"pid" => NULL
							]
						]);
					}
					$job = $this->sql->select([
						"table" => "cron_job",
						"id" => $job['cron_job_id'],
					]);
				}

				$title = <<<EOF
<span class="text-header">{$job['title']} {$this->getCronJobTableBadges($job)}</span><br/>
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
					"Last run" => [
						"html" => $job['last_run'] ?: "(Never)",
						"sm" => 2
					],
					"" => [
						"sortable" => false,
						"sm" => 3,
						"header_style" => [
							"opacity" => 0
						],
						"button" => $this->getCronJobTableButtons($job)
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

		$this->output->update("#all_cron_job", Table::generate($rows, [
			"rel_table" => $rel_table,
			"order" => true
		]));

		return true;
	}

	private function getCronJobTableBadges(array $job): string
	{
		if($job['paused']){
			$badges[] = [
				"title" => "PAUSED",
				"colour" => "blue"
			];
		} else {
			$badges[] = [
				"title" => $job['interval'],
				"colour" => "red",
				"alt" => str::title("This cron job runs ".self::INTERVALS[$job['interval']])
			];
		}
		if($job['silent']){
			$badges[] = [
				"icon" => "volume-slash",
				"alt" => "This cron job will not be logged unless there is an error",
				"colour" => "black"
			];
		}

		return Badge::generate($badges);
	}

	private function getCronJobTableButtons(array $job): array
	{
		if($job['pid']) {
			$buttons[] = [
				"alt" => "Stop this job...",
				"colour" => "red",
				"size" => "s",
				"icon" => "stop",
				"hash" => [
					"rel_table" => "cron_job",
					"rel_id" => $job["cron_job_id"],
					"action" => "kill"
				],
				"approve" => [
					"colour" => "red",
					"icon" => "stop-circle",
					"title" => "Kill cron job?",
					"message" => "This may have unintended consequences."
				]
			];
		} else {
			$buttons[] = [
				"alt" => "Execute this job...",
				"colour" => "yellow",
				"size" => "s",
				"basic" => true,
				"icon" => "play",
				"hash" => [
					"rel_table" => "cron_job",
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
		}


		if($job['paused']){
			$buttons[] = [
				"hash" => [
					"rel_table" => "cron_job",
					"rel_id" => $job["cron_job_id"],
					"action" => "pause",
					"vars" => [
						"paused" => "false"
					]
				],
				"alt" => "Unpause and resume running this job on schedule",
				"icon" => "pause",
				"colour" => "primary",
				"size" => "s",
			];
		} else {
			$buttons[] = [
				"hash" => [
					"rel_table" => "cron_job",
					"rel_id" => $job["cron_job_id"],
					"action" => "pause",
					"vars" => [
						"paused" => 1
					]
				],
				"alt" => "Pause this job from running on schedule",
				"icon" => "pause",
				"colour" => "primary",
				"size" => "s",
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
			"alt" => "See alert",
			"icon" => Icon::get("log"),
			"colour" => "info",
			"size" => "s",
			"basic" => true
		];
		$buttons[] = [
			"hash" => [
				"rel_table" => "cron_job",
				"rel_id" => $job["cron_job_id"],
				"action" => "remove"
			],
			"alt" => "Remove..",
			"icon" => Icon::get("trash"),
			"colour" => "danger",
			"size" => "s",
			"basic" => true,
			"approve" => true
		];

		return $buttons;
	}

	/**
	 * This is the function run every minute
	 * by an _actual_ cron job, to see what
	 * jobs to run.
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function runScheduled() : bool
	{
		if(!str::runFromCLI()){
			throw new \Exception("You can only run this method from the command line.");
		}

		# Set the user ID to be zero, the system user ID
		global $user_id;
		$user_id = "0";
		$_SESSION['user_id'] = $user_id;

		# Get all active (non-paused) cron jobs, in the order they appear
		if(!$cron_jobs = $this->sql->select([
			"table" => "cron_job",
			"where" => [
				"paused" => NULL
			],
			"order_by" => [
				"order" => "ASC"
			]
		])){
			//if no cron jobs are scheduled, close up shop
			return true;
		}

		# Execute all jobs (at their scheduled interval)
		$this->execute($cron_jobs);

		return true;
	}


	/**
	 * Runs a single cron job, ad-hoc.
	 *
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

		# Both \ and " must be escaped or else the command will fail
		$cron_job_json = str_replace(["'", "\\", '"'],["", "\\\\",'\\"'],json_encode($cron_job));
		// In addition, we're just stripping out single quotes, because they're not needed

		# Build the command that executes the execute method
		$cmd  = "\\Swoole\\Coroutine\\run(function(){";
		$cmd .= "require \"/var/www/html/app/settings.php\";";
		$cmd .= "\$cron_job = new \App\Common\CronJob\CronJob();";
		$cmd .= "\$cron_job->execute(\"{$cron_job_json}\", true);";
		$cmd .= "});";

		# Use the Process class to execute it with a pID that can be checked
		$process = new Process("php -r '{$cmd}'");

		# Attach the pID to the job
		$this->sql->update([
			"table" => "cron_job",
			"id" => $cron_job['cron_job_id'],
			"set" => [
				"pid" => $process->getPid()
			]
		]);

		$this->log->info([
			"icon" => "play",
			"title" => "Cron job started",
			"message" => "The <b>{$cron_job['title']}</b> cron job has been started."
		]);

		$this->hash->set(-1);

		return true;
	}

	public function kill(array $a): bool
	{
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		$cron_job = $this->sql->select([
			"table" => $rel_table,
			"id" => $rel_id,
		]);

		$process = new Process();
		$process->setPid($cron_job['pid']);

		# Ensure process is still active
		if (!$process->status()) {
			//if the process is no longer active
			$this->sql->update([
				"table" => $rel_table,
				"id" => $rel_id,
				"set" => [
					"pid" => NULL
				]
			]);
			$this->log->warning([
				"icon" => "tombstone",
				"title" => "Inactive job",
				"message" => "The <b>{$cron_job['title']}</b> job was no longer running."
			]);

			$this->hash->set(-1);

			return true;
		}

		# Kill
		if(!$process->stop()){
			//if the job could not be killed
			$this->log->error([
				"icon" => "ghost",
				"title" => "Unable to stop job",
				"message" => "Unable to stop the <b>{$cron_job['title']}</b> job. The process ID is {$cron_job['pid']}."
			]);
			return false;
		}

		# Remove the process ID
		$this->sql->update([
			"table" => $rel_table,
			"id" => $rel_id,
			"set" => [
				"pid" => NULL
			]
		]);

		$this->log->success([
			"icon" => "dizzy",
			"title" => "Job killed",
			"message" => "The <b>{$cron_job['title']}</b> job was successfully killed."
		]);

		$this->hash->set(-1);

		return true;
	}

	/**
	 * The method that actually executes the cron jobs.
	 * Can only be run from the command line.
	 * Access it either by scheduling a job or running an ad-hoc job
	 * with the run() method.
	 *
	 * @param           $a
	 * @param bool|null $ignore_interval
	 */
	public function execute($a, bool $ignore_interval = NULL): void
	{
		# Ensure this method is only run from the command line
		if(!str::runFromCLI()){
			die("You can only run this method from the command line.");
		}

		# Some cron jobs will run for a long while
		ini_set('max_execution_time', 0);

		# Load the cron job(s) to run
		if(is_string($a)){
			//If a single, ad hoc job is sent to run
			$cron_jobs[] = json_decode($a, true);
		} else if(str::isNumericArray($a)){
			//If scheduled jobs are sent to run
			$cron_jobs = $a;
		} else {
			die("No cron job sent to execute.");
		}

		# Create a new scheduler
		$scheduler = new \GO\Scheduler();

		# For each job (even if only one is supplied)
		foreach($cron_jobs as $cron_job) {

			# Function to run
			$func = function($a){
				# Extract the $cron_job array
				extract($a);

				global $user_id;
				$user_id = 0;
				$_SESSION['user_id'] = 0;

				global $SESSION;
				$SESSION = [];

				# Run the method
				try{
					# Create a new instance of the class
					$classInstance = new $cron_job['class']($this);

					# Ensure the method is available
					if(!str::methodAvailable($classInstance, $cron_job['method'], "protected")){
						throw new \Exception("The <code>".$cron_job['method']."</code> method doesn't exist or is not protected or public.");
					}

					# Run the cron job method
					$output = $classInstance->{$cron_job['method']}();
					// If the cron job method exits, this script stops here. No logs will be saved.
				}

				# Catch SQL errors
				catch(\mysqli_sql_exception $e){
					$last_query = $SESSION['query'];
					//If this variable isn't moved over to a local one, it is overwritten
					$this->log->error([
						"icon" => "database",
						"title" => "mySQL error",
						"message" => $e->getMessage()
					], ["role" => "admin"]);
					$this->log->error([
						"icon" => "code",
						"title" => "Query",
						"message" => $last_query
					], ["role" => "admin"]);
				}

				# Catch type errors
				catch(\TypeError $e){
					$this->log->error([
						"icon" => "code",
						"title" => "Type error",
						"message" => $e->getMessage()
					], ["role" => "admin"]);
				}

				# Catch all other exceptions
				catch(\Exception $e){
					$this->log->error([
						"icon" => "ethernet",
						"title" => "System error",
						"message" => $e->getMessage()
					], ["role" => "admin"]);
				}

				# Return the string (or boolean) output
				return $output;
			};

			$before = function(){
				$this->log->clearAlerts();
				$this->log->startTimer();
			};

			$then = function($output) use ($cron_job){
				/**
				 * Update the cron job:
				 * 1. Set the last run datetime
				 * 2. Remove the pID if one was set
				 */
				$this->sql->update([
					"table" => "cron_job",
					"id" => $cron_job['cron_job_id'],
					"set" => [
						"last_run" => "NOW()",
						"pid" => NULL
					],
					"user_id" => NULL
				]);

				/**
				 * If the cron job is set to silent,
				 * it will not be logged if it's
				 * successful.
				 */
				if($cron_job['silent'] && ($this->log->getStatus() == "success")){
					return true;
				}

				# Log the job as complete
				$this->sql->insert([
					"table" => "cron_log",
					"set" => [
						"cron_job_id" => $cron_job['cron_job_id'],
						"status" => $this->log->getStatus(),
						"duration" => $this->log->getDuration(),
						"output" => $this->log->getAlertMessages().str::pre($output)
					]
				]);
			};

			$args = [[
				"cron_job" => $cron_job
			]];

			if($ignore_interval){
				//If the job interval is to be ignored and the job to be run right now
				$scheduler
					->call($func, $args, $cron_job['cron_job_id'])
					->before($before)
					->then($then);
				continue;
			}

			# Otherwise, the job will only be run at its scheduled interval
			$scheduler
				->call($func, $args, $cron_job['cron_job_id'])
				->at($cron_job['interval'])
				->before($before)
				->then($then);

		}

		$scheduler->run();
	}
}