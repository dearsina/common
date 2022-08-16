<?php

namespace App\Common\OAuth2;

/**
 * An interface to unify provider classes.
 */
interface ProviderInterface {
	/**
	 * Returns a given provider's OAuth2 client so that the
	 * OAuth2 token can be collected.
	 *
	 * @return object
	 */
	public static function getOAuth2ProviderObject(): object;

	/**
	 * Given a folder name, will create a folder with that name in
	 * a given parent folder. If no parent folder is given, will
	 * create the folder in root. Returns the folder ID.
	 *
	 * @param string      $folder_name
	 * @param string|null $parent_folder_id
	 *
	 * @return string|null
	 */
	public function createFolder(string $folder_name, ?string $parent_folder_id): ?string;

	/**
	 * Uploads a given local file to a given parent folder.
	 * If no folder is given, will upload the file to root.
	 * Will return the file ID.
	 *
	 * @param array       $file
	 * @param string|null $parent_folder_id
	 *
	 * @return string|null
	 */
	public function uploadFile(array $file, ?string $parent_folder_id): ?string;

	/**
	 * Checks to see if a given folder name exists in the given parent folder.
	 * If no parent folder is given, will look in root. Will return the folder
	 * ID if there is a match, but will return NULL if the folder cannot be found.
	 *
	 * @param string      $folder_name
	 * @param string|null $parent_folder_id
	 *
	 * @return string|null
	 */
	public function folderExists(string $folder_name, ?string $parent_folder_id): ?string;

	/**
	 * Checks to see if a given file name exists in the given parent folder.
	 * If no parent folder is given, will look in root. Will return the file
	 * ID if there is a match, but will return NULL if the file cannot be found.
	 *
	 * @param string      $file_name
	 * @param string|null $parent_folder_id
	 *
	 * @return array|null
	 */
	public function fileExists(string $file_name, ?string $parent_folder_id): ?array;

	/**
	 * Given a folder ID, will return the folder name.
	 *
	 * @param string|null $folder_id
	 *
	 * @return string|null
	 */
	public function getFolderName(?string $folder_id): ?string;

	/**
	 * Given a parent folder, will return all the child folders.
	 * If a given folder ID is included, will also return all the
	 * grand children up to and included the folder and its siblings.
	 *
	 * Will return an array with name and children as keys. Name
	 * is the name of the folder, children any searched for children.
	 * The entire tree will not be mapped out, if a folder doesn't
	 * have the children array key, it doesn't mean it doesn't have
	 * any children.
	 *
	 * @param string      $parent_id
	 * @param string|null $folder_id
	 *
	 * @return ?array
	 */
	public function getFolders(string $parent_id, ?string $folder_id = NULL): ?array;
}