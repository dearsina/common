<?php

namespace App\Common\RemoteStorage;

/**
 *
 */
interface RemoteStorageInterface {
	/**
	 * Copies a file and associated pages if a page int has been supplied.
	 *
	 * @param string|null $source_location
	 * @param string      $source_container_id
	 * @param string      $source_blob_id
	 * @param string|null $target_location
	 * @param string      $target_container_id
	 * @param string      $target_blob_id
	 * @param int|null    $pages
	 *
	 * @return void
	 */
	public function copyFile(?string $source_location, string $source_container_id, string $source_blob_id, ?string $target_location, string $target_container_id, string $target_blob_id, ?int $pages = NULL): void;

	/**
	 * Publicly facing does file exist, with boolean result.
	 * Includes page.
	 *
	 *
	 * @param string      $container_id
	 * @param string      $blob_id
	 * @param int|null    $page
	 * @param string|null $location
	 *
	 * @return bool
	 */
	public function fileExists(string $container_id, string $blob_id, ?int $page = NULL, ?string $location = NULL): bool;

	/**
	 * Deletes a given subscription_id/rel_id.
	 * Will delete a given page only if a page number is included.
	 *
	 * PDFs will have each of their pages broken down to a single file JPG.
	 * If that PDF is deleted, their single file pages will remain.
	 *
	 *
	 * @param string      $container_id
	 * @param string      $blob_id
	 * @param int|null    $page
	 * @param string|null $location
	 *
	 * @return void
	 */
	public function deleteFile(string $container_id, string $blob_id, ?int $page = NULL, ?string $location = NULL): void;

	/**
	 * Given a container ID, a blob ID, and a file array, will
	 * create a blob containing the file.
	 *
	 * Should be the only used function. Will check if the container
	 * exists, create it if it doesn't and check if the file exists
	 * before attempting to create the blob.
	 *
	 * @param string $container_id
	 * @param string $blob_id
	 * @param array $file            A file array like below.
	 *                               <code>
	 *                               array (
	 *                               'name' => 'WhatsApp Image 2020-08-11 at 13.13.00.jpeg',
	 *                               'type' => 'image/jpeg',
	 *                               'tmp_name' => '/var/www/tmp/phpBKhbpJ',
	 *                               'size' => 199977,
	 *                               'page' => 3, //The page number (optional, will only apply to PDFs)
	 *                               ),
	 *                               </code>
	 *
	 * @return void
	 */
	public function setFile(string $container_id, string $blob_id, array $file): void;

	/**
	 * Compare the current instance's location with the passed location.
	 * If they're the same, return true.
	 * If they're different, return false.
	 *
	 * @param string|null $location
	 *
	 * @return bool
	 */
	public function isLocation(?string $location): bool;

	/**
	 * Get the container-blob data.
	 *
	 * @param string      $container_id
	 * @param string      $blob_id
	 * @param string|null $location
	 *
	 * @return false|string|null False means the file doesn't exist, NULL means there was an error, and the string is
	 *                           the file contents.
	 * @throws \Exception
	 */
	public function getData(string $container_id, string $blob_id, ?string $location = NULL);

	/**
	 * Gets file (blob) size in bytes, if the file exists.
	 *
	 * @param string   $container_id
	 * @param string   $blob_id
	 * @param int|null $page
	 *
	 * @return int|null
	 * @throws \Exception
	 */
	public function getFileSize(string $container_id, string $blob_id, ?int $page = NULL): ?int;

	/**
	 * Returns the temporary URL to the given container blob.
	 *
	 * @param string      $container_id
	 * @param string      $blob_id
	 * @param string|null $filename
	 * @param int|null    $page
	 * @param string|null $location
	 * @param string|null $disposition
	 *
	 * @return string
	 */
	public function getURL(string $container_id, string $blob_id, ?string $filename = NULL, ?int $page = NULL, ?string $location = NULL, ?string $disposition = "inline"): string;

	/**
	 * Save a given container/blob to a local filename.
	 *
	 * @param string      $container_id
	 * @param string      $blob_id
	 * @param array|null  $file If no `tmp_name` is supplied, one is created
	 * @param string|null $location
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function saveBlobLocally(string $container_id, string $blob_id, ?array &$file = [], ?string $location = NULL): bool;

	/**
	 * Given a string of data, will make a container/blob with it and ascribe the given metadata.
	 * Expecting the following metadata:
	 *  - content_type
	 *  - filename
	 *
	 * @param string $container_id
	 * @param string $blob_id
	 * @param string $data
	 * @param array  $metadata
	 *
	 * @throws \Exception
	 */
	public function setData(string $container_id, string $blob_id, string $data, array $metadata): void;

	/**
	 * @param string      $container_id
	 * @param string      $blob_id
	 * @param array       $file
	 * @param int         $resolution
	 * @param int|null    $quality
	 * @param string|null $rel_table If a rel_table is passed, will store the page dimensions of each page and upload
	 *                               it to the page_dimension column.
	 *
	 * @throws \ImagickException
	 */
	public function setPages(string $container_id, string $blob_id, array &$file, int $resolution = 100, int $quality = NULL, ?string $rel_table = NULL): void;

	/**
	 * Will update an existing blob with new content from a file.
	 * Assumes the container/blob exists and will error if not.
	 *
	 * @param string $container_id
	 * @param string $blob_id
	 * @param string $filename
	 *
	 * @throws \Exception
	 */
	public function updateFile(string $container_id, string $blob_id, string $filename): void;

	/**
	 * Deletes a given container (and all its contents)
	 * if it exists. Will look through all locations to ensure
	 * all instances of the container are deleted.
	 *
	 * @param string $container_id
	 *
	 * @return int Returns the number of blobs deleted across all locations.
	 * @throws \Exception
	 */
	public function deleteContainer(string $container_id): int;
}