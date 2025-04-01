<?php

namespace App\Common\Doc;

use App\Common\Output;
use App\Common\Process;
use App\Common\str;

/**
 * Converts XFA PDFs to standard PDFs.
 * Assumes Wine and Adobe Acrobat XI are
 * installed for the www-data user.
 */
class XfaPdf {
	/**
	 * Screen resolution to open the display in.
	 * Needs to be big, otherwise the screenshots
	 * will not be legible for the OCR.
	 */
	const RESOLUTION = "2560x1600x24";

	/**
	 * Wine is running as www-data, so need to set the prefix
	 * to the home directory of www-data.
	 */
	const WINE_PREFIX = "/home/www-data/.wine32";

	/**
	 * Adobe Acrobat XI needs to be copy/pasted into the Program Files directory.
	 * Installation will fail.
	 */
	const ACROBAT_PATH = self::WINE_PREFIX . "/drive_c/Program Files/Adobe/Reader 11.0/Reader/AcroRd32.exe";

	/**
	 * The privileged key needs to be enabled then disabled for Adobe Reader to work.
	 */
	const REG_PATH = "HKEY_CURRENT_USER\\Software\\Adobe\\Acrobat Reader\\11.0\\Privileged";

	/**
	 * If set to true, will print debug messages.
	 *
	 * @var bool|null
	 */
	private ?bool $debug;

	/**
	 * A log of every command and result performed to convert the XFA PDF.
	 *
	 * @var array
	 */
	private $log = [];

	/**
	 * The full file path.
	 *
	 * @var string
	 */
	private string $path;

	/**
	 * The number of seconds it took to convert the file.
	 *
	 * @var float
	 */
	private float $time;

	/**
	 * The PID of the Adobe Acrobat instance.
	 *
	 * @var string|null
	 */
	private ?string $acrobat_id;

	/**
	 * The ID of the export.
	 *
	 * @var string
	 */
	private string $id;

	/**
	 * @param bool|null   $debug
	 * @param string|null $id
	 */
	public function __construct(?bool $debug = false, ?string $id = NULL)
	{
		# Set the debug mode flag
		$this->debug = $debug;

		# Set the export ID
		$this->setId($id);
	}

	public function readWineLog(?string $logfile): void
	{
		if(!$logfile){
			$this->print("No logfile provided.");
			return;
		}

		$time = str::startTimer();
		$timer = 0;
		$threshold = 120;
		$current_lines = [];

		$this->print("Reading {$logfile} for {$threshold} seconds.");

		while($timer < $threshold){
			# Update the timer
			$timer = str::stopTimer($time);

			# Relax for 1 second
			sleep(1);

			if(!file_exists($logfile)){
				$this->print("Logfile no longer exists.");
				return;
			}

			# Ensure there is contents
			if(!$contents = file_get_contents($logfile)){
				continue;
			}

			$lines = explode("\n", $contents);

			# Ensure there are new lines
			if(!$new_lines = array_slice($lines, count($current_lines))){
				continue;
			}

			foreach($new_lines as $line){
				$this->print($line);
			}

			# Update the current lines
			$current_lines = $lines;
		}

		$this->print("Reading of {$logfile} concluded after {$threshold} seconds.");
	}

	/**
	 * Converts an XFA PDF to a PDF where each page is a corresponding screenshot at high resolution.
	 *
	 * @param string      $path
	 * @param string|null $id
	 *
	 * @return string|null The output PDF filename
	 */
	public function convert(string $path, ?string $id = NULL): ?string
	{
		# Set (a new) ID if provided
		if($id){
			$this->setId($id);
		}

		# Identify where HOME is
		$this->print("WHOAMI: " . trim(shell_exec('whoami')));
		$this->print("HOME: " . trim(getenv('HOME')));

		$envs = shell_exec('env');
		# Exclude the ones from the .env file
		$env_file = file_get_contents('/var/www/.env');
		$env_file = explode("\n", $env_file);
		$env_file = array_filter(array_map(function($line){
			# Exclude lines that start with #
			if(strpos($line, "#") === 0){
				return NULL;
			}
			# Exclude empty lines
			if(!trim($line)){
				return NULL;
			}
			return explode("=", $line)[0];
		}, $env_file));

		$envs = explode("\n", $envs);
		$envs = array_filter($envs, function($line) use ($env_file){
			$key = explode("=", $line)[0];
			if(strlen($line) > 1000){
				return false;
			}
			return !in_array($key, $env_file);
		});

		$this->print(json_encode(array_values($envs), JSON_PRETTY_PRINT));

		# Start the timer
		$this->time = str::startTimer();

		# Set the display value
		$this->setDisplay();

		# Start Xvfb
		$this->startXvfb();

		# Circumvent protected mode issue
		$this->circumventProtectedMode();

		# Convert the filename to a Windows format.
		$this->setPath($path);

		# Launch Adobe Reader
		$this->setAcrobatReader($this->path);

		# Await the Adobe Reader window
		$this->awaitReaderWindows(2, 30);

		# Close make Acrobat default reader popup
		$this->closeMakeDefaultReaderPopUp();

		# Maximise window
		$this->maximiseWindow();

		# Loop through the pages and take screenshots
		$this->loopThroughPages();

		# Close Adobe Reader and remove Xvfb display and locks
		$this->cleanUp();

		# Convert the pages to a single PDF
		$pdf = $this->convertToPdf();

		# Stop the time
		$this->time = str::stopTimer($this->time);

		# Return the output PDF filename
		return $pdf;
	}

	/**
	 * Will return the log as an array, unless $as_string is set to true, in which case it will return a string.
	 *
	 * @param bool|null $as_string
	 *
	 * @return array|string
	 */
	public function getLog(?bool $as_string = NULL)
	{
		if($as_string){
			return implode("\n", $this->log);
		}

		return $this->log;
	}

	public function getTime(?int $decimals = 2): float
	{
		return round($this->time, $decimals);
	}

	private function getCurrentAcrobatPids(): array
	{
		$current_acrobat_ids = [];

		# Get all the current processes
		if(!$result = shell_exec("ps -eo pid,cmd | grep AcroRd32.exe | grep -v grep")){
			return $current_acrobat_ids;
		}

		# Double check that each PID is related to Acrobat
		foreach(explode("\n", $result) as $line){
			if(preg_match('/^\s*(\d+)/', $line, $matches)){
				$current_acrobat_ids[] = $matches[1];
			}
		}

		return $current_acrobat_ids;
	}

	private function getNewAcrobatPid(?array $previous_acrobat_pids = []): ?string
	{
		# Get the current PIDs
		$current_acrobat_pids = $this->getCurrentAcrobatPids();

		# If the current list is no different from the previous list
		if(!array_diff($current_acrobat_pids, $previous_acrobat_pids)){
			# Then there are no new PIDs
			return NULL;
		}

		# Otherwise, return the latest PID
		return end($current_acrobat_pids);
	}

	private function getWineLogFile(): ?string
	{
		# This only applies to when we're debugging
		if(!$this->debug){
			return NULL;
		}

		$logfile = "{$_ENV['tmp_dir']}{$this->id}-wine.log";

		# If the log file hasn't been created already, create it and start the listener
		if(!file_exists($logfile)){
			// We're only interested in tracking the log file if we're opening a file (path)
			$this->print("Creating {$logfile}");

			# Create an empty file
			touch($logfile);
			chown($logfile, 'www-data');
			chmod($logfile, 0666); // make it writable

			# Use the global session ID
			global $session_id;

			# Start the listener
			Process::request([
				"rel_table" => "vendor",
				"action" => "read_wine_log",
				"vars" => [
					"logfile" => $logfile,
					"id" => $this->id,
					"session_id" => $session_id,
				]
			]);
		}

		return $logfile;
	}

	private function setAcrobatReader(?string $path = NULL, ?int $sleep = 0): ?int
	{
		if($path){
			$path = "\"{$path}\"";
		}

		// Use proc_open to capture output and get Wine PID
		$descriptors = [
			1 => ['pipe', 'w'], // stdout
			2 => ['pipe', 'w'], // stderr
		];

		# Get other Acrobat PIDs that may currently be in operation
		$other_acrobat_pids = $this->getCurrentAcrobatPids();

		# Get the log file name that will capture all async wine output
		if($logfile = $this->getWineLogFile()){
			$logfile = "> {$logfile}";
		}

		# Set the command
		$cmd = "{$this->getUserPrefixes()} {$this->getWinePrefix()} {$this->getDisplayPrefix()} wine \"" . self::ACROBAT_PATH . "\" {$path} {$logfile} 2>&1 &";

		# Log it
		$this->print("Running command: {$cmd}");

		# Use proc open so that we can get the PID
		$resource = proc_open($cmd, $descriptors, $pipes);

		// Wait a bit to let AcroRd32.exe start (or fail)
		sleep(1);

		if(!is_resource($resource)){
			$this->print("❌ Failed to launch Adobe Reader via Wine, process is stopping.");
			exit;
		}

		if(!$this->acrobat_id = $this->getNewAcrobatPid($other_acrobat_pids)){
			// If the PID is not set, then the process did not start
			$stderr = stream_get_contents($pipes[2]);
			fclose($pipes[2]);
			proc_terminate($resource);
			proc_close($resource);

			$this->print("❌ Acrobat did not start.");
			$this->print("📄 Wine stderr:\n{$stderr}");
			exit(1);
		}

		$this->print("✅ Acrobat PID: $this->acrobat_id");
		$this->runCommand("ps -p {$this->acrobat_id} -o pid,ppid,user,%cpu,%mem,etime,cmd");

		if($sleep){
			sleep($sleep);
		}

		return $this->acrobat_id;
	}

	private function cleanUp(): void
	{
		# Close Adobe Reader
		$this->killProcess($this->acrobat_id);

		# Close Xvfb
		$this->killProcess($this->xvfb_id);

		# Remove the lock files
		$this->runCommand("rm -f /tmp/.X{$this->display}-lock");

		# Remove the display
		putenv("DISPLAY=");
	}

	private function setId(?string $id = NULL): void
	{
		$this->id = $id ?: str::id("xfa_export");
		$this->print("Export ID set to {$this->id}");
	}

	private function print(string $msg): void
	{
		$this->log[] = $msg;

		if($this->debug){
			global $session_id;

			if($session_id){
				$recipients = [
					"session_id" => $session_id,
				];

				# Timestamp the message for display
				$msg = "[" . date("Y-m-d H:i:s") . "] " . $msg . PHP_EOL;

				Output::getInstance()->append("#{$this->id} code", $msg, $recipients);
			}
			else {
				echo $msg . PHP_EOL;
			}

		}
	}

	private function setDisplay(): void
	{
		# Set a display value
		$this->display = ':' . rand(0, 99);

		# Set the display environment variable
		putenv("DISPLAY={$this->display}");
		// This must be set otherwise xdotool commands fail

		# Print the display value
		$this->print("Display set to {$this->display}");
	}

	private function convertPathToWindowsFormat(string $path): string
	{
		return "Z:" . str_replace("/", "\\\\", $path);
	}

	private function setPath(string $path): void
	{
		# Ensure the path has a .pdf suffix
		if(substr($path, -4) !== ".pdf"){
			rename($path, $path . ".pdf");
			$path .= ".pdf";
		}

		$this->path = $this->convertPathToWindowsFormat($path);
		$this->print("Input file path set to {$this->path}");
	}

	/**
	 * Given an ID of a collection of PNG page images, converts them to a single PDF and returns
	 * the filename of the PDF. Will also delete the PNG images.
	 *
	 * @return string
	 */
	private function convertToPdf(): string
	{
		# Convert the images to a single PDF
		$this->runCommand("img2pdf {$_ENV['tmp_dir']}{$this->id}_page_*.png -o {$_ENV['tmp_dir']}{$this->id}.pdf");

		# Delete the images
		$this->runCommand("rm {$_ENV['tmp_dir']}{$this->id}_page_*.png");

		# Return the final filename
		return "{$_ENV['tmp_dir']}{$this->id}.pdf";
	}

	private function loopThroughPages(): void
	{
		$page = 1;
		$max_number_of_pages = 100; // safety limit
		$last_hash = NULL;

		# Get the window in focus
		$this->setWindowFocus();

		while($page <= $max_number_of_pages) {
			# Set the page filename
			$filename = sprintf("{$_ENV['tmp_dir']}%s_page_%s.png", $this->id, $page);

			# Take a screenshot of the current page
			$this->runCommand("import -display {$this->display} -window root $filename");

			# Wait 0.5s
			usleep(500000);

			# Hash the image
			$hash = md5_file($filename);

			# If the hash is the same as the last one, we're done
			if($hash === $last_hash){
				# Delete the last (duplicate) page
				unlink($filename);

				break;
			}

			# Set the last hash
			$last_hash = $hash;

			# Move the page to the next page
			$this->setWindowFocus();

			# Click the mouse
			$this->runCommand("xdotool mousemove 1000 800 click 1");
			// This will trigger the next page

			# Increment the page number
			$page++;
		}
	}

	private function maximiseWindow(): void
	{
		# Set the focus
		if($winId = $this->setWindowFocus()){
			// Returns the window ID of the last Adobe Reader window
			$this->print("Window ID: $winId");
		}

		else {
			$this->print("Unable to find Adobe Reader window");
		}

		# Go full screen
		$this->runKeySequence([
			"Alt_L",
			"v",
			"f",
		]);

		# The system needs some time to go full screen
		sleep(5);
	}

	private function closeMakeDefaultReaderPopUp(): void
	{
		$start = time();
		$timeout = 15; // seconds

		while(true) {
			# Set the focus
			$this->setWindowFocus(false, true);

			# Return
			$this->runKeySequence(["Return"]);

			# Double-check the window count
			if($this->awaitReaderWindows(1, 5)){
				// If it's just 1, we're done
				return;
			}

			# Set the focus
			$this->setWindowFocus(false, true);

			# Tab, then return
			$this->runKeySequence(["Tab", "Return"]);

			# Double-check the window count
			if($this->awaitReaderWindows(1, 5)){
				// If it's just 1, we're done
				return;
			}

			# Set the focus
			$this->setWindowFocus(false, true);

			# Escape
			$this->runKeySequence(["Escape"]);

			# Double-check the window count
			if($this->awaitReaderWindows(1, 5)){
				// If it's just 1, we're done
				return;
			}

			// Timeout check
			if((time() - $start) > $timeout){
				break;
			}
		}
	}

	private function takeScreenshot(?string $key = NULL): void
	{
		# This only applies in debug mode
		if(!$this->debug){
			return;
		}

		# Set the filename
		$filename = $_ENV['tmp_dir'] . implode("-", array_filter([
				$this->id,
				time(),
				$key,
			]));

		# Take the screenshot
		$this->runCommand("import -display {$this->display} -window root {$filename}.png");
	}

	private function runKeySequence(array $keys): void
	{
		foreach($keys as $key){
			# Press the key
			$this->runCommand("xdotool key $key");

			# Sleep for 0.5s
			usleep(500000);

			# Take screenshot
			$this->takeScreenshot($key);
		}
	}

	/**
	 * @return string|null The window ID of the (last) Adobe Reader window
	 */
	private function setWindowFocus(?bool $windowraise = true, ?bool $onlyvisible = NULL, ?string $head_or_tail = "tail"): ?string
	{
		if($onlyvisible){
			# Get all visible Adobe Reader windows
			$result = $this->runCommand("xdotool search --onlyvisible --name 'Adobe Reader'");

			# Break up the string into array values
			$windows = explode("\n", $result);

			# Remove empty lines
			$windows = array_filter($windows);

			# Assume the last window is the relevant window (ID)
			$window_id = end($windows);
		}

		else {
			$window_id = $this->runCommand("xdotool search --name 'Adobe Reader' | {$head_or_tail} -n 1");
		}

		# Set the focus to the window
		$this->runCommand("xdotool windowfocus {$window_id}");

		# Sleep for 0.5s
		//		usleep(500000);

		$this->takeScreenshot("windowfocus");

		if($windowraise){
			# Raise the window
			$this->runCommand("xdotool windowraise {$window_id}");

			# Sleep for 1s
			usleep(1000000);

			$this->takeScreenshot("windowraise");
		}

		return $window_id;
	}

	/**
	 * Awaits for at least $seconds so that the $n number of Adobe Reader windows open.
	 *
	 * @param int|null $n
	 * @param int|null $seconds
	 *
	 * @return bool Returns true if the expected number of windows are open, false otherwise.
	 */
	private function awaitReaderWindows(int $n, ?int $seconds = 10): bool
	{
		# Log what's happening
		$this->print("Awaiting $n Adobe Reader windows for $seconds seconds");

		for($i = 1; $i <= $seconds; $i = $i + 3){
			$count = (int)$this->runCommand('xdotool search --name "Adobe Reader" 2>/dev/null | wc -l', NULL, true);

			# Log the number of windows found
			$this->print("Windows found after {$i} seconds: {$count}");

			if($count){
				# Take a screenshot of the windows
				$this->takeScreenshot("awaitReaderWindows-{$n}-found-{$count}-after-{$i}s");
			}

			if($count === $n){
				return true;
			}

			$this->runCommand("xdotool search --onlyvisible --name '.*' getwindowname 2>&1", NULL, true);

			sleep(10);

			# Use the Acrobat PID to see if it's still active
			exec("ps -p {$this->acrobat_id} -o pid,ppid,user,%cpu,%mem,etime,cmd", $output, $return_var);
			if($return_var === 0){
				$this->print("✅ Acrobat PID {$this->acrobat_id} is still active.");
			}
			else {
				$this->print("❌ Acrobat PID {$this->acrobat_id} is no longer active.");
				break;
			}
		}

		# If *nothing* is found, error out
		if($count === 0){
			$this->runCommand('xdotool search --name ".*"', NULL, true);
			$this->runCommand('xdotool search --onlyvisible --name ".*" getwindowname', NULL, true);
			$this->print("❌ Timeout reached, only found {$count} windows.");
			$this->takeScreenshot("window-timeout-{$count}s");
			exit;
		}

		return false;

		# Run the bash command
		$result = $this->runCommand("bash -c 'COUNT=0; for i in {1..{$seconds}}; do COUNT=\$(xdotool search --name \"Adobe Reader\" 2>/dev/null | wc -l); if [ \"\$COUNT\" -eq {$n} ]; then echo \$COUNT; exit 0; fi; sleep 1; done; echo \$COUNT; exit 1'");

		# Log the number of windows found
		$this->print("Windows found: $result");

		# Take a screenshot of the windows
		$this->takeScreenshot("awaitReaderWindows-{$n}");

		# Return true if it matches the requested number
		return $result == $n;
	}

	/**
	 * Run a command line command and return any results.
	 * Optionally, add the WINE prefix, the DISPLAY prefix, and sleep for a number of seconds.
	 * The result is logged.
	 *
	 * @param string    $cmd
	 * @param bool|null $wine_prefix
	 * @param bool|null $display_prefix
	 * @param int|null  $sleep
	 *
	 * @return string|null
	 */
	private function runCommand(string $cmd, ?bool $wine_prefix = NULL, ?bool $display_prefix = NULL, ?int $sleep = NULL): ?string
	{
		# Update HOME
		$cmd_array[] = "HOME=/home/www-data";

		# Add the WINE prefix
		if($wine_prefix){
			$cmd_array[] = $this->getWinePrefix();
		}

		# Add the DISPLAY prefix
		if($display_prefix){
			$cmd_array[] = $this->getDisplayPrefix();
		}

		# Add the command
		$cmd_array[] = $cmd;

		# Convert to a string
		$cmd = implode(" ", $cmd_array);

		# Log it
		$this->print("Running command: $cmd");

		# Set the output object
		$output = [];

		# Execute the command
		exec($cmd, $output, $return_var);

		# Get the result
		$result = implode("\n", $output);

		# Log the result
		$this->print("Result ({$return_var}): $result");

		# Sleep if requested
		if($sleep){
			sleep($sleep);
		}

		# Return the result
		return $result;
	}

	private function killProcess(string $PID): void
	{
		$this->runCommand("sudo /bin/kill -9 {$PID}");
	}


	/**
	 * An elaborate way to start the Xvfb process, to be able to get the PID.
	 *
	 * The Xvfb process cannot be killed with proc_close() or proc_terminate(),
	 * so we have to get the PID to be able to close it after we're done.
	 *
	 * @return void
	 */
	private function startXvfb(): void
	{
		$cmd = "Xvfb {$this->display} -screen 0 " . self::RESOLUTION;

		$descriptorspec = [
			0 => ["pipe", "r"], // stdin
			1 => ["pipe", "w"], // stdout
			2 => ["pipe", "w"], // stderr
		];

		$process = proc_open($cmd, $descriptorspec, $pipes);

		if(is_resource($process)){
			# Get the status
			$status = proc_get_status($process);

			# Set the PID
			$this->xvfb_id = $status['pid'];

			$this->print("Xvfb started on {$this->display} with PID: {$this->xvfb_id}");
		}

		sleep(1);

		# Add Server Interpreted access mode for local user www-data
		$this->runCommand("xhost +SI:localuser:www-data", NULL, true);

		# Test that the display is working
		exec("xdpyinfo -display {$this->display} >/dev/null 2>&1", $output, $exitCode);
		if($exitCode === 0){
			$this->print("✅ Display {$this->display} is available.");
		}
		else {
			$this->print("❌ Display {$this->display} is NOT available (code $exitCode).");
		}
	}

	/**
	 * These are very important so that Wine can run properly.
	 *
	 * @return string
	 */
	private function getUserPrefixes(): string
	{
		$environmental_variables = [
			"LANGUAGE" => "en_US",
			"LOGNAME" => "www-data",
			"TERM" => "xterm",
			"PATH" => "/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/snap/bin",
			"SHELL" => "/usr/sbin/nologin",
			"PWD" => "/var/www/tmp",
			"USER" => "www-data",
			"LANG" => "en_US.UTF-8",
			"HOME" => "/home/www-data",
		];

		return implode(" ", array_map(function($key, $value){
			return "{$key}={$value}";
		}, array_keys($environmental_variables), $environmental_variables));
	}

	private function getWinePrefix(): string
	{
		return "WINEPREFIX=" . self::WINE_PREFIX;
	}

	private function getDisplayPrefix(): string
	{
		return "DISPLAY={$this->display}";
	}

	/**
	 * Protected mode must be disabled, but we need to enable it first, launch the reader, and then disable it.
	 * This is because the registry key is not accessible when protected mode is enabled. I'm guessing.
	 *
	 * @return void
	 */
	private function circumventProtectedMode(): void
	{
		# Get the current registry key value
		$result = $this->runCommand('wine reg query "HKCU\\Software\\Adobe\\Acrobat Reader\\11.0\\Privileged" /v bProtectedMode 2>&1', true, true);

		# 0x0 Protected Mode is DISABLED
		if(strpos($result, "0x0")){
			# Enable protected mode
			$this->runCommand("wine reg add \"" . self::REG_PATH . "\" /v bProtectedMode /t REG_DWORD /d 1 /f 2>&1");
		}

		# Launch Reader once, and get the PID
		$pid = $this->setAcrobatReader();
		//		$pid = $this->runCommand("wine \"" . self::ACROBAT_PATH . "\" > /dev/null /dev/null 2>&1 & echo $!", true, true, 3);

		# Press enter
		$this->runCommand("xdotool key Return", false, false, 1);

		# Kill the reader
		$this->runCommand("kill -9 {$pid} 2>&1");

		# Disable protected mode
		$this->runCommand("wine reg add \"" . self::REG_PATH . "\" /v bProtectedMode /t REG_DWORD /d 0 /f 2>&1");
	}
}