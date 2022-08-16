<?php

namespace App\Common\OAuth2\Provider;

use App\Common\Log;
use App\Common\OAuth2\OAuth2Handler;
use App\Common\OAuth2\Prototype;
use App\Common\OAuth2\ProviderInterface;
use App\Common\str;

/**
 * Common methods for the Google Drive API.
 *
 * @link https://github.com/thephpleague/oauth2-google
 * @link https://github.com/googleapis/google-api-php-client
 */
class GoogleDrive extends Prototype implements ProviderInterface {
	private \Google_Client $client;
	private \Google\Service\Drive $drive;

	public function __construct(array $oauth_token)
	{
		OAuth2Handler::ensureTokenIsFresh($oauth_token);
		$this->client = new \Google_Client();
		$this->client->setAccessToken([
			'access_token' => $oauth_token['token'],
			'expires_in' => $oauth_token['expires'],
		]);

		$this->drive = new \Google\Service\Drive($this->client);
	}

	/**
	 * Needs to remain public, because it is called from the OAuth2Handler class,
	 * when refreshing a token.
	 *
	 * @return \League\OAuth2\Client\Provider\Google
	 */
	public static function getOAuth2ProviderObject(): \League\OAuth2\Client\Provider\Google
	{
		return new \League\OAuth2\Client\Provider\Google([
			'clientId' => $_ENV['google_oauth_client_id'],
			'clientSecret' => $_ENV['google_oauth_client_secret'],
			'redirectUri' => "https://app.{$_ENV['domain']}/oauth2.php",
			"accessType" => "offline",
			"prompt" => "consent", # Forces consent (and a refresh token) every time
			"scopes" => [
				"https://www.googleapis.com/auth/drive.metadata.readonly",
				"https://www.googleapis.com/auth/drive.file",
			],
		]);
	}

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
	public function createFolder(string $folder_name, ?string $parent_folder_id = "root"): string
	{
		try {
			$resource = new \Google\Service\Drive\DriveFile([
				'name' => $folder_name,
				'parents' => [$parent_folder_id],
				'mimeType' => "application/vnd.google-apps.folder",
			]);

			$result = $this->drive->files->create($resource);
		}

		catch(\Exception $e){
			$this->throwError($e, "%s error when creating a new folder called {$folder_name}: %s");
		}

		return $result->id;
	}

	/**
	 * Uploads a given local file to a given parent folder.
	 * If no folder is given, will upload the file to root.
	 * Will return the file ID.
	 *
	 * @param array       $file
	 * @param string|null $parent_folder_id
	 *
	 * @return string|null
	 * @throws \Exception
	 */
	public function uploadFile(array $file, ?string $parent_folder_id = "root"): ?string
	{
		try {
			$resource = new \Google\Service\Drive\DriveFile([
				'name' => $file['name'],
				'parents' => [$parent_folder_id],
			]);

			$result = $this->drive->files->create($resource, [
				'mimeType' => $file['type'],
				'data' => file_get_contents($file['tmp_name']),
				'uploadType' => 'multipart',
			]);
		}

		catch(\Exception $e){
			$this->throwError($e, "%s error when uploading {$file['name']}: %s");
		}

		return $result->id;
	}

	/**
	 * Checks to see if a given folder name exists in the given parent folder.
	 * If no parent folder is given, will look in root. Will return the folder
	 * ID if there is a match, but will return NULL if the folder cannot be found.
	 *
	 * @param string      $folder_name
	 * @param string|null $parent_folder_id
	 *
	 * @return string|null
	 * @throws \Exception
	 */
	public function folderExists(string $folder_name, ?string $parent_folder_id = "root"): ?string
	{
		# Escape single quotes
		$folder_name = str_replace("'", "\'", $folder_name);

		try {
			$response = $this->drive->files->listFiles([
				'q' => implode(" and ", [
					"mimeType='application/vnd.google-apps.folder'",
					"'{$parent_folder_id}' in parents",
					"name='{$folder_name}'",
					"trashed=false",
				]),
				'spaces' => 'drive',
				'fields' => 'nextPageToken, files(id, name, parents)',
			]);
			if(!$response->files){
				return NULL;
			}
		}

		catch(\Exception $e){
			$error = json_decode($e->getMessage(), true);

			# If the folder cannot be found (404 error)
			if($error['error']['code'] == "404"){
				return NULL;
			}

			$this->throwError($e, "%s error when looking for the {$folder_name} folder: %s");
		}

		foreach($response->files as $file){
			$matches[$file->id] = $file->name;
		}

		# Return the key of the first matching folder
		return array_key_first($matches);
	}

	/**
	 * Checks to see if a given file name exists in the given parent folder.
	 * If no parent folder is given, will look in root. Will return the file
	 * ID if there is a match, but will return NULL if the file cannot be found.
	 *
	 * @param string      $file_name
	 * @param string|null $parent_folder_id
	 *
	 * @return array|null
	 * @throws \Exception
	 */
	public function fileExists(string $file_name, ?string $parent_folder_id = "root"): ?array
	{
		# Escape single quotes
		$file_name = str_replace("'", "\'", $file_name);

		try{
			$response = $this->drive->files->listFiles([
				'q' => implode(" and ", [
					"'{$parent_folder_id}' in parents",
					"name='{$file_name}'",
					"trashed=false",
				]),
				'spaces' => 'drive',
				'fields' => 'nextPageToken, files(id, name, parents, md5Checksum)',
			]);

			if(!$response->files){
				return NULL;
			}
		}

		catch(\Exception $e){
			$error = json_decode($e->getMessage(), true);

			# If the file cannot be found (404 error)
			if($error['error']['code'] == "404"){
				return NULL;
			}

			$this->throwError($e, "%s error when looking for the {$file_name} file: %s");
		}

		foreach($response->files as $file){
			$matches[] = [
				"id" => $file->id,
				"name" => $file->name,
				"parents" => $file->parents,
				"md5" => $file->md5Checksum,
			];
		}

		# Return the first matching file
		return reset($matches);
	}

	/**
	 * Given a folder ID, will return the folder name.
	 *
	 * @param string|null $folder_id
	 *
	 * @return string|null
	 */
	public function getFolderName(?string $folder_id): ?string
	{
		if(!$folder_id){
			return $folder_id;
		}

		if($folder_id == "root"){
			return $folder_id;
		}

		try {
			$response = $this->drive->files->get($folder_id, [
				"fields" => "name",
			]);
		}

		catch(\Exception $e) {
			Log::getInstance()->error([
				"title" => "Error getting folder name",
				"message" => $e->getMessage(),
			]);
		}

		return $response['name'];
	}

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
	 * @return array|null
	 */
	public function getFolders(string $parent_id, ?string $folder_id = NULL): ?array
	{
		try {
			# Get all the children for this parent ID
			$children = $this->getChildren($parent_id);

			# If the parent ID is not root, just return the children
			if($parent_id != "root"){
				return $children;
			}

			# Set the root up
			$folders[$parent_id] = [
				"name" => "Root",
				"children" => $children,
			];

			# If a designated destination folder ID has not been selected, we're done
			if(!$folder_id){
				return $folders;
			}

			# If there *is* a destination folder ID, set it as the ID
			$id = $folder_id;

			# Load it as the first step in the lineage
			$lineage[] = $id;

			# Go through generations to get to the folder on the root
			while(!$folders[$parent_id]['children'][$id]) {

				# Get the parent (to get *their* parent)
				$response = $this->drive->files->get($id, [
					"fields" => "name, parents",
				]);

				# If it doesn't have a parent, the *root* must be the destination folder
				if(!$response['parents']){
					# Set the root folder as true
					$folders[$parent_id]['checked'] = true;
					# And we're done
					return $folders;
				}

				# Get the parent ID
				$id = reset($response['parents']);
				/**
				 * Having more than 1 parent was removed in 30 Sept 2020, so we're
				 * going to ignore the parents array and just use the first value.
				 * @link https://developers.google.com/drive/api/guides/multi-parenting
				 */

				# Add the parent to the lineage
				$lineage[] = $id;

				# Failsafe, *just* in case
				$i++;
				if($i == 25){
					break;
				}
			}

			# Now that we have the lineage, grab the last item (the folder on the root)
			$id = array_pop($lineage);

			# Make that folder the parent
			$parent = &$folders[$parent_id]['children'][$id];

			while($lineage) {
				# Load its children
				$parent['children'] = $this->getChildren($id);

				# Now get the ID of the relevant child
				$id = array_pop($lineage);

				# Make *that* child the parent
				$parent = &$parent['children'][$id];

				# Failsafe, *just* in case
				$i++;
				if($i == 25){
					break;
				}
			}

			# Repeat the process until all the children have been mapped out


			# At least, mark the final child as checked
			$parent['checked'] = true;
		}

		catch(\Exception $e) {
			Log::getInstance()->error([
				"title" => "Error loading folders",
				"message" => $e->getMessage(),
			]);
		}

		return $folders;
	}

	/**
	 * Given a parent folder ID, gets the child folder names and IDs.
	 * Only used by the `getFolders` method.
	 *
	 * @param \Google\Service\Drive $service
	 * @param string                $parent_id
	 *
	 * @return array|null
	 */
	private function getChildren(string $parent_id): ?array
	{
		$pageToken = NULL;

		try {
			do {
				$response = $this->drive->files->listFiles([
					'q' => implode(" and ", [
						"mimeType='application/vnd.google-apps.folder'",
						"'{$parent_id}' in parents",
						"trashed=false",
					]),
					'spaces' => 'drive',
					'pageToken' => $pageToken,
					'fields' => 'nextPageToken, files(id, name, parents)',
				]);
				foreach($response->files as $file){
					$children[$file->id] = [
						"name" => $file->name,
					];
				}
				$pageToken = $response->pageToken;
			} while($pageToken != NULL);
		}

		catch(\Exception $e) {
			Log::getInstance()->error([
				"title" => "Error loading folders",
				"message" => $e->getMessage(),
			]);
		}

		return $children;
	}

	/**
	 * Handles any errors that the file and folder methods may throw.
	 * Will still throw an error at the end, but will first store the
	 * complete message to the log and add optional context to the
	 * error messages that can at times be quite sparse.
	 *
	 * @param \Exception  $e
	 * @param string|null $context
	 *
	 * @throws \Exception
	 */
	private function throwError(\Exception $e, ?string $context = NULL): void
	{
		$error = $e->getMessage();
		if(str::isJson($error)){
			$error_array = json_decode($error, true);
			$error_code = $error_array['error']['code'];
			$error_message = $error_array['error']['message'];
		}

		if($context){
			$narrative = sprintf($context, $error_code, $error_message);
		}

		else {
			$narrative = $error_message ?: $error;
		}

		Log::getInstance()->error([
			"display" => false,
			"title" => "Google OAuth2 error",
			"message" => $narrative,
			"trace" => $error
		]);

		throw new \Exception($narrative);
	}
}