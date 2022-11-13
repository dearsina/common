<?php

namespace App\Common\PrintRun;

use App\Common\Output;
use App\Common\SQL\Factory;
use App\Common\str;
use App\UI\Button;
use App\UI\ListGroup;
use App\UI\Progress;

/**
 * The print run methods work to inform the end user
 * that PDFs are being produced, and to prevent the server
 * from being overloaded with too many concurrent PDF
 * production requests.
 */
class PrintRun extends \App\Common\Prototype {
	/**
	 * Get a single existing print run ID based on the
	 * MD5 of the file being produced and the session ID
	 * of the user producing it.
	 *
	 * @param string $md5
	 *
	 * @return string|null
	 * @throws \Exception
	 */
	private static function getExistingPrintRunId(string $md5): ?string
	{
		# Use the global session ID (not the local one)
		global $session_id;

		if(!$print_run = Factory::getInstance()->select([
			"table" => "print_run",
			"where" => [
				"session_id" => $session_id,
				"md5" => $md5,
				"stopped" => NULL
			],
			"limit" => 1
		])){
			return NULL;
		}

		return $print_run['print_run_id'];
	}

	/**
	 * If there is another google-chrome process running,
	 * wait until it's done. Check every 1 second.
	 */
	private static function wait(): ?int
	{
		while(shell_exec("pgrep -af ^/usr/bin/google-chrome")){
			$slept++;
			sleep(1);
		}

		return $slept;
	}

	/**
	 * Start a print run. This method will also
	 * be called on re-runs, in which case the
	 * existing ID will be returned.
	 *
	 * @param string      $md5
	 * @param string|null $filename
	 * @param int|null    $rerun
	 *
	 * @return string|null
	 * @throws \Exception
	 */
	public static function start(string $md5, ?string $filename, ?int $rerun): ?string
	{
		# Use the global session ID (not the local one)
		global $session_id;

		# If this is a rerun, just return the existing print run ID
		if($rerun){
			if(!$print_run_id = self::getExistingPrintRunId($md5)){
				return NULL;
			}

			# If another doc is being produced at the moment, hold your horses
			self::wait();

			# Return the existing print run ID
			return $print_run_id;
		}

		# Create a new print run
		$print_run_id = Factory::getInstance()->insert([
			"table" => "print_run",
			"set" => [
				"session_id" => $session_id,
				"md5" => $md5,
				"filename" => $filename
			]
		]);

		# Update the print runs window, but only if a filename has been passed
		if($filename){
			self::updateWindow($print_run_id);
		}

		# If another doc is being produced at the moment, hold your horses
		$slept = self::wait();
		// Also count how many seconds we had to wait for the log

		# When we're ready to start, let's go!
		Factory::getInstance()->update([
			"table" => "print_run",
			"id" => $print_run_id,
			"set" => [
				"slept" => $slept,
				"started" => "NOW()"
			]
		]);

		# Return the new print run ID
		return $print_run_id;
	}

	/**
	 * Stops a print run. Is triggered both when the production is
	 * completed, and if errors out after a max number of re-runs.
	 *
	 * @param string   $md5
	 * @param int|null $rerun
	 *
	 * @throws \Exception
	 */
	public static function stop(string $md5, ?int $rerun): void
	{
		if(!$print_run_id = self::getExistingPrintRunId($md5)){
			//if no matching print run exists, pencils down
			return;
		}

		# Stop the run
		Factory::getInstance()->update([
			"table" => "print_run",
			"id" => $print_run_id,
			"set" => [
				"reruns" => $rerun,
				"seconds" => str::stopTimer(),
				"stopped" => "NOW()"
			]
		]);

		# Remove the print run from the process window
		Output::getInstance()->remove("#print-run-{$print_run_id}", ["session_id" => $session_id]);

		# Remove the entire progress window (if there are no other active runs)
		if(!self::getAllActivePrintRuns()){
			Output::getInstance()->remove("#print-run", ["session_id" => $session_id]);
		}
	}

	/**
	 * Is only called if the user wants to cancel the print run.
	 *
	 * @param array $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function remove(array $a): bool
	{
		extract($a);

		# Use the global session ID (not the local one)
		global $session_id;

		# Stop the run
		Factory::getInstance()->update([
			"table" => "print_run",
			"id" => $rel_id,
			"set" => [
				"cancelled" => true,
				"stopped" => "NOW()"
			]
		]);

		# Remove the print run from the process window
		Output::getInstance()->remove("#print-run-{$rel_id}", ["session_id" => $session_id]);

		# Remove the progress window (if there are no other active runs)
		if(!self::getAllActivePrintRuns()){
			Output::getInstance()->remove("#print-run", ["session_id" => $session_id]);
		}

		# Return the URL to what it was
		$this->hash->set(-1);

		# Don't refresh the whole page
		$this->hash->silent();

		return true;
	}

	/**
	 * Given a user session ID, will return any active print runs.
	 *
	 * @return array|null
	 * @throws \Exception
	 */
	private static function getAllActivePrintRuns(): ?array
	{
		# Use the global session ID (not the local one)
		global $session_id;

		return Factory::getInstance()->select([
			"table" => "print_run",
			"where" => [
				"session_id" => $session_id,
				"stopped" => NULL,
				["filename", "IS NOT", NULL]
			],
		]);
	}

	/**
	 * Creates or updates the process window to keep the user updated
	 * on what print runs they have going.
	 *
	 * @param string|null $print_run_id
	 *
	 * @throws \Exception
	 */
	private static function updateWindow(?string $print_run_id = NULL): void
	{
		# Use the global session ID (not the local one)
		global $session_id;

		# Get all active print runs for this session ID
		if(!$print_runs = self::getAllActivePrintRuns()){
			// If there are no active running print runs that need displaying

			# Remove any existing windows (because there are no active runs)
			Output::getInstance()->remove("#print-run", ["session_id" => $session_id]);

			# Pencils down
			return;
		}

		# Creates items for each print run currently active
		foreach($print_runs as $print_run){
			$items[$print_run['print_run_id']] = [
				"id" => "print-run-{$print_run['print_run_id']}",
				"title" => [
					"title" => $print_run['filename'],
					"style" => [
						"font-size" => "75%"
					]
				],
				"body" => progress::generate([
					"height" => "px",
					"width" => "100%",
					"label" => false,
					"colour" => "primary",
					"seconds" => 30,
				]),
				"button" => Button::generic("remove", "print_run", $print_run['print_run_id'])
			];
		}

		# Append a print run if others are running
		if($print_run_id && count($print_runs) > 1){
			// If a particular print run is being added, and there are other print runs in operation

			# Just append the one print run, because presumably, the window has already been created
			Output::getInstance()->append("#print-run-items > ul", ListGroup::generate([
				"flush" => true,
				"items" => [$items[$print_run_id]]
			]), ["session_id" => $session_id]);

			# And we're done
			return;
		}

		# Otherwise, create the window with all the currently active print runs
		Output::getInstance()->window(self::progressWindow($items), ["session_id" => $session_id]);
	}

	/**
	 * Generate and return the progress window.
	 *
	 * @param array $items
	 *
	 * @return string
	 * @throws \Exception
	 */
	private static function progressWindow(array $items): string
	{
		$window = new \App\UI\Card\Window([
			"id" => "print-run",
			"size" => "s",
			"icon" => "print",
			"header" => [
				"id" => "print-run-header",
				"title" => "Producing documents"
			],
			"items" => [
				"id" => "print-run-items",
				"flush" => true,
				"items" => $items
			],
			"dismissible" => true,
			"draggable" => true,
			"resizable" => false,
			"style" => [
				"top" => "10%",
				"right" => "2%",
				"left" => "unset",
				"position" => "fixed",
				"min-height" => "unset"
			]
		]);

		return $window->getHTML();
	}
}