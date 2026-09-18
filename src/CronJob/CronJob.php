<?php


namespace App\Common\CronJob;

use App\Common\Prototype;
use App\Common\href;
use App\Common\str;
use App\Common\CronLog\RunFilter;
use App\UI\Badge;
use App\UI\Icon;
use App\UI\Page;
use App\UI\Table;
use Cron\CronExpression;

/**
 * Class CronJob
 * @package App\Common\CronJob
 */
class CronJob extends Prototype {
	public ?string $db = RuntimePolicy::DB;

	public const INTERVALS = [
		'@yearly' => 'Yearly',
		'@monthly' => 'Monthly',
		'@weekly' => 'Weekly',
		'@daily' => 'Daily, at midnight UTC',
		'0 2 * * *' => 'Daily, at 2am UTC',
		'0 4 * * *' => 'Daily, at 4am UTC',
		'@hourly' => 'Hourly',
		'0,30 * * * *' => "Every 30 minutes",
		'*/15 * * * *' => "Every 15 minutes",
		'*/10 * * * *' => "Every 10 minutes",
		'*/9 * * * *' => "Every 9 minutes",
		'*/8 * * * *' => "Every 8 minutes",
		'*/7 * * * *' => "Every 7 minutes",
		'*/6 * * * *' => "Every 6 minutes",
		'*/5 * * * *' => "Every 5 minutes",
		'*/4 * * * *' => "Every 4 minutes",
		'*/3 * * * *' => "Every 3 minutes",
		'*/2 * * * *' => "Every 2 minutes",
		'* * * * *' => "Every minute",
	];

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
	 * View all Cron jobs. Will open a modal.
	 *
	 * @param array $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function all(array $a): bool
	{
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		$page = new Page([
			"title" => "Cron job control centre",
			"icon" => Icon::get("cron_job"),
		]);

		$page->setGrid([[
			"html" => $this->card()->summary($a),
		], [
			"html" => $this->card()->running($a),
		]]);

		$page->setGrid(["html" => $this->card()->all($a)]);

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
	public function edit(array $a): bool
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
	public function new(array $a): bool
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
	public function insert(array $a): bool
	{
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		$vars = is_array($vars ?? NULL) ? $vars : [];
		$this->validateJobConfiguration($vars);
		$vars['order'] = $this->getOrder($rel_table);

		$this->sql->insert([
			"db" => RuntimePolicy::DB,
			"table" => $rel_table,
			"set" => $vars,
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
	public function update(array $a): bool
	{
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		$vars = is_array($vars ?? NULL) ? $vars : [];
		$this->validateJobConfiguration($vars);
		$vars["last_status"] = NULL;

		$this->sql->update([
			"db" => RuntimePolicy::DB,
			"table" => $rel_table,
			"set" => $vars,
			"id" => $rel_id,
		]);

		# Closes the (top-most) modal
		$this->output->closeModal();

		# Get the latest update of the CronJobs table
		$this->updateCronJobs($a);

		return true;
	}

	private function validateJobConfiguration(array &$vars): void
	{
		$vars["timeout_seconds"] = RuntimePolicy::timeout($vars["timeout_seconds"] ?? NULL);

		try {
			CronExpression::factory((string)($vars["interval"] ?? ""));
		}
		catch(\Throwable $throwable) {
			throw new \InvalidArgumentException("The cron interval is invalid: " . $throwable->getMessage(), 0, $throwable);
		}

		$class = (string)($vars["class"] ?? "");
		$method = (string)($vars["method"] ?? "");
		if(!$class || !class_exists($class)){
			throw new \InvalidArgumentException("The selected cron class cannot be loaded.");
		}

		if(!$this->isPublicCronMethod($class, $method)){
			throw new \InvalidArgumentException("The selected cron method is unavailable.");
		}
	}

	/**
	 * Validate a cron target without constructing it. Some cron classes, such as
	 * the WebSocket server, intentionally cannot be instantiated by a web request.
	 */
	private function isPublicCronMethod(string $class, string $method): bool
	{
		if(!$method || !method_exists($class, $method)){
			return false;
		}

		try {
			$reflection = new \ReflectionMethod($class, $method);
		}
		catch(\ReflectionException) {
			return false;
		}

		return $reflection->isPublic()
			&& !$reflection->isConstructor()
			&& !$reflection->isDestructor();
	}

	/**
	 * Same as update, but won't close the modal.
	 *
	 * @param array $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function pause(array $a): bool
	{
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		$this->sql->update([
			"db" => RuntimePolicy::DB,
			"table" => $rel_table,
			"set" => $vars,
			"id" => $rel_id,
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
		if((new Runtime())->getActiveRuns((string)$rel_id)){
			throw new \RuntimeException("This cron job has an active or queued run. Cancel it before removing the schedule.");
		}

		$this->sql->remove([
			"db" => RuntimePolicy::DB,
			"table" => $rel_table,
			"id" => $rel_id,
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
	public function getClassMethods($a): bool
	{
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		$options[] = [
			"id" => "",
			"title" => "",
		];

		if(!$methods = str::getMethodsFromClass($vars['class'])){
			$this->output->setOptions($options, "No methods exist for this class.");
			return true;
		}

		foreach($methods as $method){
			$options[] = [
				"id" => $method['name'],
				"title" => $method['name'],
			];
		}

		# Order the methods by title
		str::multidimensionalOrderBy($options, [
			"title" => "ASC",
		]);

		$class = explode("\\", $vars['class']);
		$this->output->setOptions($options, "Select a method for the " . end($class) . " class.");
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
	public function reorder($a): bool
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
	public function updateCronJobs(array $a): bool
	{
		if(!$this->user->is("admin")){
			return true;
		}

		$rel_table = $a["rel_table"] ?? "cron_job";
		$cron_jobs = $this->normaliseRows($this->sql->select([
			"db" => RuntimePolicy::DB,
			"table" => "cron_job",
			"order_by" => [
				"order" => "ASC",
			],
		]));

		$runtime = new Runtime();
		$active_runs = $runtime->getActiveRuns();
		$active_by_job = [];
		foreach($active_runs as $run){
			$active_by_job[$run["cron_job_id"]][] = $run;
		}

		$rows = [];
		foreach($cron_jobs as $job){
			$active_run = $active_by_job[$job["cron_job_id"]][0] ?? NULL;
			$latest_run = $this->getLatestRun($job["cron_job_id"]);
			$stats = $this->getJobRunStats($job["cron_job_id"]);
			$title = htmlspecialchars((string)$job["title"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
			$desc = htmlspecialchars((string)$job["desc"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
			$class_method = htmlspecialchars((string)$job["class"] . "::" . (string)$job["method"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
			$schedule = self::INTERVALS[$job["interval"]] ?? $job["interval"];

			$rows[] = [
				"order" => $job["order"],
				"id" => $job["cron_job_id"],
				"Job" => [
					"html" => "<span class=\"text-header\">{$title} {$this->getCronJobTableBadges($job, $active_run, $latest_run)}</span><br>"
						. "<span class=\"text-desc\">{$desc}</span><br>"
						. "<code class=\"small\">{$class_method}</code>",
					"hash" => [
						"rel_table" => $rel_table,
						"rel_id" => $job["cron_job_id"],
						"action" => "edit",
					],
				],
				"Schedule" => [
					"html" => htmlspecialchars((string)$schedule, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8")
						. "<br><span class=\"small text-muted\">Hard limit: "
						. RuntimePolicy::formatDuration(RuntimePolicy::timeout($job["timeout_seconds"] ?? NULL)) . "</span>",
					"sm" => 2,
				],
				"Last run" => [
					"html" => $this->formatLatestRun($latest_run),
					"sm" => 2,
				],
				"30-day health" => [
					"html" => $this->formatRunStats($stats),
					"sm" => 2,
				],
				"" => [
					"sortable" => false,
					"sm" => 2,
					"header_style" => ["opacity" => 0],
					"button" => $this->getCronJobTableButtons($job, $active_run),
				],
			];
		}

		$jobs_html = $rows
			? Table::generate($rows, [
			"rel_table" => $rel_table,
			"rel_db" => RuntimePolicy::DB,
			"order" => true,
		])
			: "<div class=\"text-muted text-center p-4\">No cron jobs are configured.</div>";

		$this->output->update("#all_cron_job", $jobs_html);
		$this->output->update("#currently_running_cron_jobs", $this->formatActiveRuns($active_runs));
		$this->output->update("#cron_job_summary", $this->formatSchedulerSummary($cron_jobs, $active_runs));

		return true;
	}

	public function setOrder(array $a, $silent = NULL): void
	{
		$a["rel_db"] = RuntimePolicy::DB;
		parent::setOrder($a, $silent);
	}

	private function getCronJobTableBadges(array $job, ?array $active_run = NULL, ?array $latest_run = NULL): string
	{
		$badges = [];
		if($job['paused']){
			$badges[] = [
				"title" => "PAUSED",
				"colour" => "blue",
			];
		}
		else if($active_run){
			$badges[] = [
				"title" => strtoupper(str_replace("_", " ", $active_run["status"])),
				"colour" => $this->statusColour($active_run["status"]),
			];
		}
		else {
			$badges[] = [
				"title" => $job['interval'],
				"colour" => "red",
				"alt" => str::title("This cron job runs " . (self::INTERVALS[$job['interval']] ?? $job['interval'])),
			];
		}
		if(!$active_run && $latest_run){
			$badges[] = [
				"title" => strtoupper(str_replace("_", " ", $latest_run["status"])),
				"colour" => $this->statusColour($latest_run["status"]),
				"alt" => "Most recent execution status",
			];
			if(($latest_run["result_status"] ?? RuntimePolicy::RESULT_NONE) !== RuntimePolicy::RESULT_NONE){
				$badges[] = [
					"title" => "RESULT " . strtoupper(str_replace("_", " ", (string)$latest_run["result_status"])),
					"colour" => $this->resultColour((string)$latest_run["result_status"]),
					"alt" => "Most recent reported business result",
				];
			}
		}
		if($job['silent']){
			$badges[] = [
				"icon" => "volume-slash",
				"alt" => "Routine notifications are silent; executions are still retained",
				"colour" => "black",
			];
		}

		return Badge::generate($badges);
	}

	private function getCronJobTableButtons(array $job, ?array $active_run = NULL): array
	{
		$buttons = [];
		if($active_run){
			$buttons[] = [
				"alt" => "Stop this job...",
				"colour" => "red",
				"size" => "s",
				"icon" => "stop",
				"hash" => [
					"rel_table" => "cron_job",
					"rel_id" => $job["cron_job_id"],
					"action" => "kill",
				],
				"approve" => [
					"colour" => "red",
					"icon" => "stop-circle",
					"title" => "Kill cron job?",
					"message" => "This may have unintended consequences.",
				],
			];
		}
		else {
			$buttons[] = [
				"alt" => "Execute this job...",
				"colour" => "yellow",
				"size" => "s",
				"basic" => true,
				"icon" => "play",
				"hash" => [
					"rel_table" => "cron_job",
					"rel_id" => $job["cron_job_id"],
					"action" => "run",
				],
				"approve" => [
					"colour" => "yellow",
					"icon" => "play",
					"title" => "Execute cron job?",
					"message" => "Start running this job? Depending on the job, this could take a while.",
				],
			];
		}


		if($job['paused']){
			$buttons[] = [
				"hash" => [
					"rel_table" => "cron_job",
					"rel_id" => $job["cron_job_id"],
					"action" => "pause",
					"vars" => [
						"paused" => "false",
					],
				],
				"alt" => "Unpause and resume running this job on schedule",
				"icon" => "pause",
				"colour" => "primary",
				"size" => "s",
			];
		}
		else {
			$buttons[] = [
				"hash" => [
					"rel_table" => "cron_job",
					"rel_id" => $job["cron_job_id"],
					"action" => "pause",
					"vars" => [
						"paused" => 1,
					],
				],
				"alt" => "Pause this job from running on schedule",
				"icon" => "pause",
				"colour" => "primary",
				"size" => "s",
				"basic" => true,
			];
		}

		$buttons[] = [
			"hash" => [
				"rel_table" => "cron_log",
				"action" => "all",
				"vars" => [
					"cron_job_id" => $job["cron_job_id"],
				],
			],
			"alt" => "View every run, status, result, and duration for this job",
			"icon" => Icon::get("log"),
			"colour" => "info",
			"size" => "s",
			"basic" => true,
		];
		if(!$active_run){
			$buttons[] = [
				"hash" => [
					"rel_table" => "cron_job",
					"rel_id" => $job["cron_job_id"],
					"action" => "remove",
				],
				"alt" => "Remove..",
				"icon" => Icon::get("trash"),
				"colour" => "danger",
				"size" => "s",
				"basic" => true,
				"approve" => true,
			];
		}

		return $buttons;
	}

	private function getLatestRun(string $cron_job_id): ?array
	{
		$runs = $this->normaliseRows($this->sql->select([
			"db" => RuntimePolicy::DB,
			"table" => "cron_log",
			"where" => ["cron_job_id" => $cron_job_id],
			"order_by" => [
				"created" => "DESC",
				"cron_log_id" => "DESC",
			],
			"start" => 0,
			"length" => 1,
		]));
		return $runs ? reset($runs) : NULL;
	}

	private function getJobRunStats(string $cron_job_id): array
	{
		$since = date("Y-m-d H:i:s", strtotime("-30 days"));
		$groups = $this->normaliseRows($this->sql->select([
			"db" => RuntimePolicy::DB,
			"columns" => [
				"status",
				"Runs" => ["count", "cron_log_id"],
				"Average" => ["avg", "duration"],
			],
			"table" => "cron_log",
			"where" => [
				"cron_job_id" => $cron_job_id,
				["created", ">=", $since],
				["status", "IN", [
					RuntimePolicy::STATUS_SUCCESS,
					RuntimePolicy::STATUS_WARNING,
					RuntimePolicy::STATUS_FAILED,
					RuntimePolicy::STATUS_TIMED_OUT,
				]],
			],
			"group_by" => "status",
		]));
		$output_totals = $this->normaliseRows($this->sql->select([
			"db" => RuntimePolicy::DB,
			"columns" => [
				"ReportedFailures" => ["sum", "reported_failures"],
				"ReportedWarnings" => ["sum", "reported_warnings"],
			],
			"table" => "cron_log",
			"where" => [
				"cron_job_id" => $cron_job_id,
				["created", ">=", $since],
			],
		]));
		$output_totals = $output_totals ? reset($output_totals) : [];
		$result_failure_runs = (int)$this->sql->select([
			"db" => RuntimePolicy::DB,
			"count" => true,
			"table" => "cron_log",
			"where" => [
				"cron_job_id" => $cron_job_id,
				"result_status" => RuntimePolicy::RESULT_FAILURE,
				["created", ">=", $since],
			],
		]);

		$successes = 0;
		$run_count = 0;
		$duration_total = 0.0;
		$duration_count = 0;
		foreach($groups as $group){
			$count = (int)($group["Runs"] ?? 0);
			$run_count += $count;
			if(in_array($group["status"], [RuntimePolicy::STATUS_SUCCESS, RuntimePolicy::STATUS_WARNING], true)){
				$successes += $count;
			}
			if(is_numeric($group["Average"] ?? NULL)){
				$duration_total += (float)$group["Average"] * $count;
				$duration_count += $count;
			}
		}

		return [
			"runs" => $run_count,
			"successes" => $successes,
			"average_duration" => $duration_count ? $duration_total / $duration_count : NULL,
			"result_failure_runs" => $result_failure_runs,
			"reported_failures" => (int)($output_totals["ReportedFailures"] ?? 0),
			"reported_warnings" => (int)($output_totals["ReportedWarnings"] ?? 0),
		];
	}

	private function formatLatestRun(?array $run): string
	{
		if(!$run){
			return "<span class=\"text-muted\">Never run</span>";
		}

		$when = $run["finished_at"] ?: $run["started_at"] ?: $run["scheduled_for"] ?: $run["created"];
		$status = htmlspecialchars(str::title(str_replace("_", " ", (string)$run["status"])), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
		$duration = RuntimePolicy::formatDuration($run["duration"] ?? NULL);
		$ago = $when ? str::ago($when) : "unknown time";
		$result_status = (string)($run["result_status"] ?? RuntimePolicy::RESULT_NONE);
		$result = htmlspecialchars(str::title(str_replace("_", " ", $result_status)), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
		return "<span class=\"{$this->statusTextClass($run['status'])}\">Execution: {$status}</span>"
			. "<br><span class=\"{$this->resultTextClass($result_status)}\">Result: {$result}</span>"
			. "<br><span class=\"small text-muted\">{$duration}; {$ago}</span>";
	}

	private function formatRunStats(array $stats): string
	{
		if(!$stats["runs"]){
			return "<span class=\"text-muted\">No completed runs</span>";
		}

		$rate = round(($stats["successes"] / $stats["runs"]) * 100, 1);
		$colour = $rate >= 98 ? "text-success" : ($rate >= 90 ? "text-warning" : "text-danger");
		$result_class = $stats["result_failure_runs"] ? "text-danger" : "text-muted";
		$result_line = $stats["result_failure_runs"]
			? "{$stats['result_failure_runs']} runs reported failures ({$stats['reported_failures']} items)"
			: "No reported failures";
		return "<span class=\"{$colour}\"><b>{$rate}%</b> execution success</span>"
			. "<br><span class=\"{$result_class}\">{$result_line}</span>"
			. "<br><span class=\"small text-muted\">{$stats['runs']} runs; avg "
			. RuntimePolicy::formatDuration($stats["average_duration"]) . "</span>";
	}

	private function formatActiveRuns(array $runs): string
	{
		if(!$runs){
			return "<div class=\"text-muted text-center p-4\">No active or queued runs.</div>";
		}

		$rows = [];
		foreach($runs as $run){
			if($run["started_at"]){
				$elapsed_from = $run["started_at"];
				$elapsed_label = "Runtime";
			}
			else if($run["launched_at"]){
				$elapsed_from = $run["launched_at"];
				$elapsed_label = "Launch wait";
			}
			else {
				$elapsed_from = $run["scheduled_for"] ?: $run["created"];
				$elapsed_label = "Queue wait";
			}
			$elapsed = $elapsed_from ? max(0, time() - strtotime($elapsed_from)) : NULL;
			$deadline = $this->formatDeadline($run["deadline_at"] ?? NULL);
			$title = htmlspecialchars((string)($run["job_title"] ?: $run["cron_job_id"]), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
			$display_status = $run["status"] === RuntimePolicy::STATUS_QUEUED && $run["launched_at"]
				? "starting"
				: $run["status"];

			$rows[] = [
				"Status" => ["html" => Badge::generate([[
					"title" => strtoupper(str_replace("_", " ", $display_status)),
					"colour" => $this->statusColour($run["status"]),
				]])],
				"Job" => [
					"html" => "<b>{$title}</b><br><code class=\"small\" style=\"word-wrap: anywhere;\">" . htmlspecialchars((string)$run["cron_log_id"], ENT_QUOTES, "UTF-8") . "</code>",
				],
				"Trigger" => ["html" => str::title((string)$run["trigger_type"])],
				"Elapsed" => [
					"html" => "<span class=\"small text-muted\">{$elapsed_label}</span><br>"
						. RuntimePolicy::formatDuration($elapsed),
				],
				"Deadline" => ["html" => "<span class=\"small\">{$deadline}</span>"],
				"Process" => [
					"html" => $run["worker_pid"]
						? "Worker {$run['worker_pid']}<br><span class=\"small text-muted\">Supervisor {$run['supervisor_pid']}</span>"
						: ($run["supervisor_pid"] ? "Starting ({$run['supervisor_pid']})" : "Queued"),
				],
				"" => [
					"sortable" => false,
					"button" => [[
						"title" => "Cancel this run...",
						"icon" => "stop",
						"colour" => "danger",
						"size" => "s",
						"hash" => [
							"rel_table" => "cron_log",
							"rel_id" => $run["cron_log_id"],
							"action" => "cancel",
						],
						"approve" => [
							"title" => "Cancel cron run?",
							"message" => "The worker and all of its child processes will be terminated.",
							"colour" => "red",
						],
					]],
				],
			];
		}

		return Table::generate($rows);
	}

	private function formatDeadline(?string $deadline): string
	{
		return $deadline
			? str::ago($deadline, true)
			: "Starts when capacity is available";
	}

	private function formatSchedulerSummary(array $jobs, array $active_runs): string
	{
		$recent_runs = $this->normaliseRows($this->sql->select([
			"db" => RuntimePolicy::DB,
			"columns" => [
				"status",
				"Runs" => ["count", "cron_log_id"],
			],
			"table" => "cron_log",
			"where" => [["created", ">=", date("Y-m-d H:i:s", strtotime("-24 hours"))]],
			"group_by" => "status",
		]));
		$failures = 0;
		$successes = 0;
		foreach($recent_runs as $recent_run){
			$count = (int)($recent_run["Runs"] ?? 0);
			if(in_array($recent_run["status"], [RuntimePolicy::STATUS_FAILED, RuntimePolicy::STATUS_TIMED_OUT], true)){
				$failures += $count;
			}
			if(in_array($recent_run["status"], [RuntimePolicy::STATUS_SUCCESS, RuntimePolicy::STATUS_WARNING], true)){
				$successes += $count;
			}
		}
		$queued = count(array_filter($active_runs, static fn($run) => $run["status"] === RuntimePolicy::STATUS_QUEUED));
		$running = count($active_runs) - $queued;
		$paused = count(array_filter($jobs, static fn($job) => !empty($job["paused"])));
		$reported_failure_runs = (int)$this->sql->select([
			"db" => RuntimePolicy::DB,
			"count" => true,
			"table" => "cron_log",
			"where" => [
				"result_status" => RuntimePolicy::RESULT_FAILURE,
				["created", ">=", date("Y-m-d H:i:s", strtotime("-24 hours"))],
			],
		]);

		$metrics = [
			["label" => "Running", "value" => $running, "class" => $running ? "text-primary" : "text-muted", "scope" => "running"],
			["label" => "Queued", "value" => $queued, "class" => $queued ? "text-warning" : "text-muted", "scope" => "queued"],
			["label" => "Succeeded (24h)", "value" => $successes, "class" => "text-success", "scope" => "succeeded_24h"],
			["label" => "Failed / timed out (24h)", "value" => $failures, "class" => $failures ? "text-danger" : "text-success", "scope" => "failed_24h"],
			["label" => "Reported failures (24h)", "value" => $reported_failure_runs, "class" => $reported_failure_runs ? "text-danger" : "text-success", "scope" => "reported_failures_24h"],
			["label" => "Configured", "value" => count($jobs), "class" => "text-info", "scope" => "configured"],
			["label" => "Paused", "value" => $paused, "class" => $paused ? "text-warning" : "text-muted", "scope" => "paused"],
		];

		$html = $this->formatDispatcherState() . "<div class=\"row text-center\">";
		foreach($metrics as $metric){
			$value = href::a([
				"hash" => [
					"rel_table" => "cron_log",
					"action" => "all",
					"vars" => [RunFilter::SCOPE_KEY => $metric["scope"]],
				],
				"html" => $metric["value"],
				"class" => $metric["class"],
				"style" => ["font-size" => "1.65rem", "font-weight" => 600],
				"alt" => "View these runs in the execution ledger",
			]);
			$html .= "<div class=\"col-6 col-md-4 mb-3\">{$value}"
				. "<div class=\"small text-muted\">{$metric['label']}</div></div>";
		}
		return $html . "</div>";
	}

	private function formatDispatcherState(): string
	{
		$state = $this->sql->select([
			"db" => RuntimePolicy::DB,
			"table" => "cron_scheduler_state",
			"id" => "00000000-0000-0000-0000-000000000001",
		]);
		if(!$state){
			return "<div class=\"alert alert-danger mb-3\"><b>Dispatcher heartbeat missing.</b> The scheduler migration may not be installed.</div>";
		}

		$status = (string)($state["status"] ?? "never_run");
		$last_tick = $state["last_finished"] ?: $state["last_started"];
		$last_timestamp = $last_tick ? (strtotime((string)$last_tick) ?: 0) : 0;
		$is_stale = !$last_timestamp || $last_timestamp < time() - 180;
		$is_failed = $status === "failed";
		$is_running = $status === "running" && !$is_stale;

		$colour = ($is_stale || $is_failed) ? "danger" : ($is_running ? "info" : "success");
		$title = $is_stale
			? "Dispatcher heartbeat is stale"
			: ($is_failed ? "The last dispatcher tick failed" : ($is_running ? "Dispatcher tick in progress" : "Dispatcher is healthy"));
		$details = $last_tick
			? "Last heartbeat " . str::ago((string)$last_tick)
				. " (" . htmlspecialchars((string)$last_tick, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") . " UTC)"
			: "No dispatcher tick has been recorded.";
		if(!$is_stale && !$is_running && is_numeric($state["duration"] ?? NULL)){
			$details .= "; tick duration " . RuntimePolicy::formatDuration($state["duration"]);
		}
		if(!empty($state["hostname"])){
			$details .= "; host " . htmlspecialchars((string)$state["hostname"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
		}
		if($is_failed && !empty($state["error_message"])){
			$details .= "<br><code>" . htmlspecialchars((string)$state["error_message"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") . "</code>";
		}
		$retention_days = (int)($state["retention_days"] ?? RuntimePolicy::DEFAULT_RETENTION_DAYS);
		$details .= $retention_days === 0
			? "<br>Automatic run-history pruning is disabled."
			: "<br>History retention: {$retention_days} days"
				. (!empty($state["last_pruned_at"]) ? "; last pruned " . str::ago((string)$state["last_pruned_at"]) : "; awaiting first prune");

		return "<div class=\"alert alert-{$colour} mb-3\"><b>{$title}.</b> {$details}</div>";
	}

	private function statusColour(?string $status): string
	{
		return match($status){
			RuntimePolicy::STATUS_SUCCESS => "green",
			RuntimePolicy::STATUS_WARNING => "yellow",
			RuntimePolicy::STATUS_RUNNING => "blue",
			RuntimePolicy::STATUS_QUEUED => "grey",
			RuntimePolicy::STATUS_CANCELLING => "orange",
			RuntimePolicy::STATUS_FAILED, RuntimePolicy::STATUS_TIMED_OUT => "red",
			RuntimePolicy::STATUS_CANCELLED, RuntimePolicy::STATUS_MISSED, RuntimePolicy::STATUS_SKIPPED_OVERLAP => "black",
			default => "grey",
		};
	}

	private function statusTextClass(?string $status): string
	{
		return match($status){
			RuntimePolicy::STATUS_SUCCESS => "text-success",
			RuntimePolicy::STATUS_WARNING => "text-warning",
			RuntimePolicy::STATUS_RUNNING => "text-primary",
			RuntimePolicy::STATUS_FAILED, RuntimePolicy::STATUS_TIMED_OUT => "text-danger",
			default => "text-muted",
		};
	}

	private function resultColour(?string $status): string
	{
		return match($status){
			RuntimePolicy::RESULT_SUCCESS => "green",
			RuntimePolicy::RESULT_WARNING => "yellow",
			RuntimePolicy::RESULT_FAILURE => "red",
			RuntimePolicy::RESULT_INFO => "blue",
			default => "grey",
		};
	}

	private function resultTextClass(?string $status): string
	{
		return match($status){
			RuntimePolicy::RESULT_SUCCESS => "text-success",
			RuntimePolicy::RESULT_WARNING => "text-warning",
			RuntimePolicy::RESULT_FAILURE => "text-danger",
			RuntimePolicy::RESULT_INFO => "text-primary",
			default => "text-muted",
		};
	}

	private function normaliseRows($rows): array
	{
		if(!$rows || !is_array($rows)){
			return [];
		}
		return array_is_list($rows) ? $rows : [$rows];
	}

	/**
	 * This is the function run every minute
	 * by an _actual_ cron job, to see what
	 * jobs to run.
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function runScheduled(): bool
	{
		return (new Runtime())->runScheduled();
	}


	/**
	 * Runs a single cron job, ad-hoc.
	 *
	 * @param $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function run($a)
	{
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		if(!$cron_job = $this->sql->select([
			"db" => RuntimePolicy::DB,
			"table" => $rel_table,
			"id" => $rel_id,
		])){
			throw new \Exception("The cron job cannot be found.");
		}

		$run_id = (new Runtime())->queueManualRun($cron_job["cron_job_id"]);

		$this->log->info([
			"icon" => "play",
			"title" => "Cron job queued",
			"message" => "The <b>{$cron_job['title']}</b> cron job was queued as run <code>{$run_id}</code>.",
		]);

		$request_vars = is_array($a["vars"] ?? NULL) ? $a["vars"] : [];
		if(!empty($request_vars[RunFilter::TRACK_KEY])){
			$this->hash->set([
				"rel_table" => "cron_log",
				"action" => "all",
				"vars" => [
					"cron_job_id" => $cron_job["cron_job_id"],
					RunFilter::TRACK_KEY => $run_id,
				],
			]);
		}
		else {
			$this->hash->set(-1);
		}

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
			"db" => RuntimePolicy::DB,
			"table" => $rel_table,
			"id" => $rel_id,
		]);

		$count = (new Runtime())->requestJobCancellation($cron_job["cron_job_id"]);
		if(!$count){
			$this->log->warning([
				"icon" => "tombstone",
				"title" => "No active run",
				"message" => "The <b>{$cron_job['title']}</b> job has no active or queued run to cancel.",
			]);
		}
		else {
			$this->log->success([
				"icon" => "stop-circle",
				"title" => "Cancellation requested",
				"message" => "Cancellation was requested for " . str::pluralise_if($count, "active run", true) . " of <b>{$cron_job['title']}</b>.",
			]);
		}

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
	public function execute($a, ?bool $ignore_interval = NULL): void
	{
		str::runFromCLI(true);
		$cron_jobs = is_string($a) ? [json_decode($a, true)] : $a;
		if(!is_array($cron_jobs)){
			throw new \InvalidArgumentException("No cron job was supplied for execution.");
		}

		$runtime = new Runtime();
		foreach($cron_jobs as $cron_job){
			if(!empty($cron_job["cron_job_id"])){
				$runtime->queueManualRun($cron_job["cron_job_id"]);
			}
		}
	}

	public function getClassOptions(array $a): bool
	{
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		# If no term is passed, don't search
		if(!$vars['term']){
			return true;
		}

		$paths = array_merge(
			str::getComposerPsr4Paths("App\\Common\\"),
			str::getComposerPsr4Paths("App\\"),
			str::getComposerPsr4Paths("API\\"),
		);

		if(!$paths){
			$paths = [
				__DIR__ . "/../../",
				($_SERVER['DOCUMENT_ROOT'] ?? "") . "/../app/",
				($_SERVER['DOCUMENT_ROOT'] ?? "") . "/../api/",
			];
		}

		$paths = array_filter(array_unique(array_map(function($path){
			return realpath($path) ?: $path;
		}, $paths)), "is_dir");

		$classes = [];
		foreach($paths as $path){
			$classes = array_merge($classes, str::getClassesFromPath($path, NULL, $vars['term']) ?: []);
		}

		# Sort them alphabetically
		$classes = array_unique($classes);
		sort($classes);

		# Load them in an options array
		foreach($classes as $m){
			$class_options[$m] = $m;
		}

		# Return them to the caller
		$this->output->setOptions($class_options, "Select a class");
        return true;
	}
}
