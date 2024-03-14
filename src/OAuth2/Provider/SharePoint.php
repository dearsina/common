<?php

namespace App\Common\OAuth2\Provider;

use App\Common\Log;
use App\Common\OAuth2\OAuth2Handler;
use App\Common\str;

/**
 * Manage OneDrive file uploads. Relies on the Microsoft Graph API
 * and the Microsoft Graph API PHP SDK.
 *
 * Requires:
 * @link https://github.com/microsoftgraph/msgraph-sdk-php
 *       https://github.com/TheNetworg/oauth2-azure
 *
 *       https://developer.microsoft.com/en-us/graph/graph-explorer
 */
class SharePoint extends \App\Common\OAuth2\Prototype implements \App\Common\OAuth2\FileProviderInterface {
	/**
	 * Takes the token, ensures its fresh, stores it in the class global
	 * and loads the graph.
	 *
	 * @param array $oauth_token
	 *
	 * @throws \App\Common\Exception\BadRequest
	 */
	public function __construct(array $oauth_token)
	{
		# Ensure the token is fresh
		OAuth2Handler::ensureTokenIsFresh($oauth_token);

		# Store the fresh token in the class global
		$this->oauth_token = $oauth_token;

		# Load the graph
		$this->graph = new \Microsoft\Graph\Graph();

		# Set the access token
		$this->graph->setAccessToken($oauth_token['token']);
	}

	/**
	 * Ensure the token is fresh.
	 *
	 * @return void
	 * @throws \App\Common\Exception\BadRequest
	 */
	public function ensureTokenIsFresh(): void
	{
		# If the token has yet to expire, we're good
		if($this->oauth_token['expires'] > strtotime("now")){
			return;
		}

		# Get a new token
		OAuth2Handler::ensureTokenIsFresh($this->oauth_token);

		# Set the refreshed access token
		$this->graph->setAccessToken($this->oauth_token['token']);
	}

	/**
	 * @inheritDoc
	 */
	public static function getOAuth2ProviderObject(?bool $force_refresh_token = NULL): object
	{
		$provider = new \TheNetworg\OAuth2\Client\Provider\Azure([
			'clientId' => $_ENV['microsoft_graph_client_id'],
			'clientSecret' => $_ENV['microsoft_graph_client_secret'],
			'redirectUri' => "https://app.{$_ENV['domain']}/oauth2.php",
			"prompt" => $force_refresh_token ? "consent" : NULL,
			'scopes' => [
				"https://graph.microsoft.com/Sites.Manage.All",
				"https://graph.microsoft.com/Sites.Read.All",
				'offline_access',
			],
			'defaultEndPointVersion' => '2.0',
		]);

		// Set to use v2 API
		$provider->defaultEndPointVersion = \TheNetworg\OAuth2\Client\Provider\Azure::ENDPOINT_VERSION_2_0;

		return $provider;
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
	 * @link https://stackoverflow.com/questions/61591023/create-a-folder-in-specific-directory-in-sharepoint-drive-using-graph-sdk
	 */
	public function createFolder(string $folder_name, ?string $parent_folder_id = "root"): ?string
	{
		$key = json_decode($parent_folder_id, true);

		# Format the name for saving
		$this->formatNameForSaving($folder_name);

		# Ensure the token is fresh before we make the request
		$this->ensureTokenIsFresh();

		try {
			$response = $this->graph->setApiVersion("v1.0")
				->createRequest("POST", "/sites/{$key['site_id']}/drive/items/{$key['item_id']}/children")
				->addHeaders([
					"Content-Type" => "application/json",
				])
				->attachBody(json_encode([
					"name" => $folder_name,
					"folder" => [],
				], JSON_FORCE_OBJECT))
				->execute();

			$response_array = $response->getBody();
		}

		catch(\Exception $e) {
			$this->throwError($e, "%s error creating {$folder_name} folder in parent folder ID {$parent_folder_id}: %s");
		}

		return json_encode([
			"type" => "item",
			"site_id" => $key['site_id'],
			"drive_id" => $key['drive_id'],
			"list_id" => $key['list_id'],
			"item_id" => $response_array['id'],
		]);
	}

	/**
	 * @inheritDoc
	 */
	public function uploadFile(array $file, ?string $parent_folder_id = "root"): ?string
	{
		$key = json_decode($parent_folder_id, true);

		# Ensure the token is fresh before we make the request
		$this->ensureTokenIsFresh();

		try {
			$response = $this->graph->setApiVersion("v1.0")
				->createRequest("PUT", "/sites/{$key['site_id']}/drive/items/{$key['item_id']}:/{$file['name']}:/content")
				->addHeaders([
					"Content-Type" => $file['type'],
				])
				->upload($file['tmp_name']);

			$response_array = $response->getBody();
		}

		catch(\Exception $e) {
			# If we're getting a 404 on the sites endpoint, try the drive endpoint instead
			if($e->getCode() == "404"){
				try {
					$response = $this->graph->setApiVersion("v1.0")
						->createRequest("PUT", "/drives/{$key['drive_id']}/items/{$key['item_id']}:/{$file['name']}:/content")
						->addHeaders([
							"Content-Type" => $file['type'],
						])
						->upload($file['tmp_name']);

					$response_array = $response->getBody();
				}
				catch(\Exception $ee) {
					$this->throwError($ee, "%s error when uploading {$file['name']} into the folder ID {$parent_folder_id} using the drives endpoint: %s");
				}
			}
			# For all other error codes, throw the error
			else {
				$this->throwError($e, "%s error when uploading {$file['name']} into the folder ID {$parent_folder_id} using the sites endpoint: %s");
			}
		}

		return json_encode([
			"type" => "item",
			"site_id" => $key['site_id'],
			"drive_id" => $key['drive_id'],
			"list_id" => $key['list_id'],
			"item_id" => $response_array['id'],
		]);
	}

	/**
	 * Formats a folder or file name to conform with Microsoft Graph.
	 *
	 * @param string      $name
	 * @param string|null $wildcard The replacement for illegal characters. Defaults to "*".
	 *
	 * @return void
	 */
	private function formatNameForFiltering(string &$name, ?string $wildcard = "*"): void
	{
		# Remove leading and trailing spaces
		$name = trim($name);

		# Convert illegal characters to wildcard
		$name = str_replace(["'", "&", "?", "%", "#", "/"], $wildcard, $name);

		# Convert spaces to %20
		$name = str_replace(" ", "%20", $name);
	}

	/**
	 * Format a (folder) name for saving.
	 * This will remove illegal characters and leading and trailing spaces.
	 *
	 * @param string $name
	 *
	 * @return void
	 */
	private function formatNameForSaving(string &$name): void
	{
		# Remove leading and trailing spaces
		$name = trim($name);

		# Remove illegal characters
		$name = str::filter_filename($name);
	}

	/**
	 * @inheritDoc
	 * @link https://docs.microsoft.com/en-us/graph/query-parameters
	 */
	public function folderExists(string $folder_name, ?string $parent_folder_id = "root"): ?string
	{
		$key = json_decode($parent_folder_id, true);

		# Format the name to search
		$this->formatNameForFiltering($folder_name);

		# Ensure the token is fresh before we make the request
		$this->ensureTokenIsFresh();

		try {
			$endpoint = str::generate_url("/sites/{$key['site_id']}/drive/items/{$key['item_id']}/children", [
				"filter" => "(name eq '{$folder_name}')",
				"select" => "id,name,sharepointIds,folder",
			]);
			$response = $this->graph->setApiVersion("v1.0")
				->createRequest("GET", $endpoint)
				->execute();

			$response_array = $response->getBody();
		}

		catch(\Exception $e) {
			# If we're getting a 404 on the sites endpoint, try the drive endpoint instead
			if($e->getCode() == "404"){
				try {
					$endpoint = str::generate_url("/drives/{$key['drive_id']}/items/{$key['item_id']}/children", [
						"filter" => "(name eq '{$folder_name}')",
						"select" => "id,name,sharepointIds,folder",
					]);
					$response = $this->graph->setApiVersion("v1.0")
						->createRequest("GET", $endpoint)
						->execute();

					$response_array = $response->getBody();
				}
				catch(\Exception $ee) {
					$this->throwError($e, "%s error when looking for the {$folder_name} folder using the drive endpoint: %s", $endpoint);
				}
			}
			# For all other error codes, throw the error
			else {
				$this->throwError($e, "%s error when looking for the {$folder_name} folder using the sites endpoint: %s", $endpoint);
			}
		}

		if(!$response_array['value']){
			return NULL;
		}

		foreach($response_array['value'] as $item){
			# We're only interested in folders
			if(!$item['folder']){
				continue;
			}

			# Return the first matching folder
			return json_encode([
				"type" => "item",
				"site_id" => $key['site_id'],
				"drive_id" => $key['drive_id'],
				"list_id" => $key['list_id'],
				"item_id" => $item['id'],
			]);
		}

		# If we get here, we didn't find a matching folder
		return NULL;
	}

	/**
	 * @inheritDoc
	 * @link https://docs.microsoft.com/en-us/graph/query-parameters
	 */
	public function fileExists(string $file_name, ?string $parent_folder_id = "root"): ?array
	{
		$key = json_decode($parent_folder_id, true);

		# Format the name for search
		$this->formatNameForFiltering($file_name);

		# Ensure the token is fresh before we make the request
		$this->ensureTokenIsFresh();

		try {
			$endpoint = str::generate_url("/sites/{$key['site_id']}/drive/items/{$key['item_id']}/children", [
				"filter" => "(name eq '{$file_name}')",
				"select" => "id,name,size",
			]);
			$response = $this->graph->setApiVersion("v1.0")
				->createRequest("GET", $endpoint)
				->execute();

			$response_array = $response->getBody();
		}

		catch(\Exception $e) {
			# If we're getting a 404 on the sites endpoint, try the drive endpoint instead
			if($e->getCode() == "404"){
				try {
					$endpoint = str::generate_url("/drives/{$key['drive_id']}/items/{$key['item_id']}/children", [
						"filter" => "(name eq '{$file_name}')",
						"select" => "id,name,size",
					]);
					$response = $this->graph->setApiVersion("v1.0")
						->createRequest("GET", $endpoint)
						->execute();

					$response_array = $response->getBody();
				}
				catch(\Exception $ee) {
					$this->throwError($ee, "%s error when looking for the {$file_name} file using the drives endpoint: %s", $endpoint);
				}
			}
			# For all other error codes, throw the error
			else {
				$this->throwError($e, "%s error when looking for the {$file_name} file using the sites endpoint: %s", $endpoint);
			}
		}

		if(!$response_array['value']){
			return NULL;
		}

		foreach($response_array['value'] as $file){
			return [
				"id" => $file['id'],
				"name" => $file['name'],
				"size" => $file['size'],
				//SharePoint doesn't give MD5, so we're going to use size instead
			];
		}
	}

	/**
	 * Sharepoint has sites, that have drives, that have items that have more items.
	 * This method will return the name of the item or drive passed in the folder_id
	 * param.
	 *
	 * @inheritDoc
	 */
	public function getFolderName(?string $folder_id): ?string
	{
		# The folder name must be a JSON string
		if(!str::isJson($folder_id)){
			return NULL;
		}

		$key = json_decode($folder_id, true);

		# Ensure the token is fresh before we make the request
		$this->ensureTokenIsFresh();

		try {
			# If we're at one level above the root, we need to get the drive name (not the item name)
			if(!$key['item_id']){
				$drive = $this->getDrive($key['site_id'], $key['drive_id']);
				return $drive['name'];
			}

			$response = $this->graph->setApiVersion("v1.0")
				->createRequest("GET", "/drives/{$key['drive_id']}/items/{$key['item_id']}?select=name")
				->execute();

			$response_array = $response->getBody();
		}

		catch(\Exception $e) {
			$response = json_decode($e->getResponse()->getBody()->getContents(), true);
			if($response['error']['code'] = "itemNotFound"){
				Log::getInstance()->warning([
					"title" => "Destination folder removed",
					"message" => "The destination folder on your Microsoft SharePoint connection cannot be found.
					Please choose another destination folder. Otherwise the connection will not work.",
				]);
				return "[REMOVED]";
			}
			$this->throwError($e, "%s error when getting name of folder ID {$folder_id}: %s");
		}

		return $response_array['name'];
	}

	/**
	 * @inheritDoc
	 */
	public function getFolders(string $parent_id, ?string $folder_id = NULL): ?array
	{
		if($folder_id){
			return $this->getParents($folder_id);
		}

		return $this->getChildren($parent_id);
	}

	private function getSites(): ?array
	{
		# Ensure the token is fresh before we make the request
		$this->ensureTokenIsFresh();

		try {
			$response = $this->graph->createCollectionRequest("GET", "/sites?search=*")
				->setReturnType(\Microsoft\Graph\Model\Site::class);

			# Get the first site
			$sites = $response->getPage();

			# Get any subsequent sites
			while(!$response->isEnd()) {
				$sites = array_merge($sites, $response->getPage());
			}

			foreach($sites as $site){
				$key = [
					"type" => "site",
					"site_id" => $site->getId(),
				];
				$children[json_encode($key)] = [
					"name" => $site->getDisplayName(),
				];
			}
		}

		catch(\Exception $e) {
			$this->throwError($e, "%s error when loading the root sites: %s");
		}

		return $children;
	}

	private function getDrives(string $site_id): ?array
	{
		# Ensure the token is fresh before we make the request
		$this->ensureTokenIsFresh();

		try {
			$response = $this->graph->createCollectionRequest("GET", "/sites/{$site_id}/drives?select=id,driveType,name")
				->setReturnType(\Microsoft\Graph\Model\ItemReference::class);

			# Get the first drive
			$drives = $response->getPage();

			# Get any subsequent drives
			while(!$response->isEnd()) {
				$drives = array_merge($drives, $response->getPage());
			}

			foreach($drives as $drive){
				$key = [
					"type" => "drive",
					"site_id" => $site_id,
					"drive_id" => $drive->getId(),
				];
				$children[json_encode($key)] = [
					"name" => $drive->getName(),
				];
			}
		}

		catch(\Exception $e) {
			$this->throwError($e, "%s error when loading the drives for site ID {$site_id}: %s");
		}

		return $children;
	}

	private function getDrive(string $site_id, string $drive_id): ?array
	{
		# Ensure the token is fresh before we make the request
		$this->ensureTokenIsFresh();

		try {
			$response = $this->graph->createCollectionRequest("GET", "/sites/{$site_id}/drives/{$drive_id}?select=id,driveType,name,sharepointIds,folder")
				->setReturnType(\Microsoft\Graph\Model\ItemReference::class);

			# Get the drive
			return $response->getPage()->getProperties();
		}

		catch(\Exception $e) {
			$this->throwError($e, "%s error when loading the {$drive_id} from the site ID {$site_id}: %s");
		}
	}

	private function getFolderItems(string $site_id, string $drive_id): ?array
	{
		# Ensure the token is fresh before we make the request
		$this->ensureTokenIsFresh();

		try {
			$response = $this->graph->createCollectionRequest("GET", "/drives/{$drive_id}/root/children?select=id,name,sharepointIds")
				->setReturnType(\Microsoft\Graph\Model\ItemReference::class);

			# Get the first list
			$items = $response->getPage();

			# Get any subsequent lists
			while(!$response->isEnd()) {
				$items = array_merge($items, $response->getPage());
			}

			foreach($items as $item){
				$item = $item->getProperties();

				# We're only interested in folders
				if(!$item['folder']){
					continue;
				}

				$key = [
					"type" => "item",
					"site_id" => $site_id,
					"drive_id" => $drive_id,
					"list_id" => $item['sharepointIds']['listId'],
					"item_id" => $item['id'],
				];
				$children[json_encode($key)] = [
					"name" => $item['name'],
				];
			}
		}

		catch(\Exception $e) {
			$this->throwError($e, "%s error when loading the children for drive ID {$drive_id}: %s");
		}

		return $children;
	}

	private function getItems(string $site_id, string $drive_id, string $item_id): ?array
	{
		# Ensure the token is fresh before we make the request
		$this->ensureTokenIsFresh();

		try {
			$response = $this->graph->createCollectionRequest("GET", "/drives/{$drive_id}/items/{$item_id}/children?select=id,name,sharepointIds,folder")
				->setReturnType(\Microsoft\Graph\Model\ItemReference::class);

			# Get the first drive
			$items = $response->getPage();

			# Get any subsequent drives
			while(!$response->isEnd()) {
				$items = array_merge($items, $response->getPage());
			}

			foreach($items as $item){
				$item = $item->getProperties();

				# We're only interested in folders
				if(!$item['folder']){
					continue;
				}

				$key = [
					"type" => "item",
					"site_id" => $site_id,
					"drive_id" => $drive_id,
					"list_id" => $item['sharepointIds']['listId'],
					"item_id" => $item['id'],
				];
				$children[json_encode($key)] = [
					"name" => $item['name'],
				];
			}
		}

		catch(\Exception $e) {
			$this->throwError($e, "%s error when loading the children for item ID {$item_id}: %s");
		}

		return $children;
	}

	private function getParents(string $folder_id, ?array $lineage = []): ?array
	{
		$key = json_decode($folder_id, true);

		# Ensure the token is fresh before we make the request
		$this->ensureTokenIsFresh();

		try {
			if($key['item_id']){
				$response = $this->graph->createCollectionRequest("GET", "/drives/{$key['drive_id']}/items/{$key['item_id']}?select=id,name,folder,sharepointIds,parentReference")
					->setReturnType(\Microsoft\Graph\Model\ItemReference::class);


				# Get the one item this call should be returning
				$item = $response->getPage()->getProperties();
			}

			else {
				return $this->getParentDriveAndSite($folder_id, []);
			}
		}

		catch(\Exception $e) {
			if($e->getCode() == 404){
				# If the folder has been removed, we'll get a 404
				Log::getInstance()->warning([
					"title" => "Destination folder removed",
					"message" => "The destination folder on your Microsoft SharePoint connection cannot be found.
					Please choose another destination folder. Otherwise the connection will not work.",
				]);
				return $this->getSites();
			}
			$this->throwError($e, "%s error when loading the children for list ID {$folder_id}: %s");
		}

		if(!$lineage){
			$lineage = [
				$folder_id => [
					"name" => $item['name'],
					"checked" => true,
				],
			];
		}

		/**
		 * If the parentReference is empty, we've reached the end of the lists.
		 * The two lasts steps are drive and site, which we'll need to get
		 * separately.
		 *
		 * parentReference will be an array with the following keys:
		 * driveId: "b!8QPUp4TntUuzL1IGWYjFLQkwIkm99KZIhDQHWpVn2M-8AqHa3QAGQJpWTUQi5eXX"
		 * driveType: "documentLibrary"
		 */
		if(!$item['parentReference']['id']){
			// The last two levels down are the drive and the site
			return $this->getParentDriveAndSite($folder_id, $lineage);
		}

		$key = [
			"type" => "item",
			"site_id" => $item['parentReference']['siteId'],
			"drive_id" => $item['parentReference']['driveId'],
			"list_id" => $item['parentReference']['sharepointIds']['listId'],
			"item_id" => $item['parentReference']['id'],
		];

		$lineage = [
			json_encode($key) => [
				"name" => $item['parentReference']['name'],
				"children" => $lineage,
			],
		];

		return $this->getParents(json_encode($key), $lineage);
	}

	/**
	 * Gets the drive and site for the given folder ID and adds it to the lineage.
	 * Will return all the sites with the lineage added to the relevant site.
	 *
	 *
	 * @param string     $folder_id
	 * @param array|null $lineage
	 *
	 * @return array|null
	 */
	private function getParentDriveAndSite(string $folder_id, ?array $lineage): ?array
	{
		$key = json_decode($folder_id, true);

		# Get the drive
		$drive = $this->getDrive($key['site_id'], $key['drive_id']);
		// We're only really doing this to get the drive name, we already have the drive ID

		# Set the drive key
		$drive_key = [
			"type" => "drive",
			"site_id" => $key['site_id'],
			"drive_id" => $key['drive_id'],
		];

		# Add it to the lineage
		if(!$lineage){
			// If the drive is highest up in the hierarchy, check it
			$lineage = [
				json_encode($drive_key) => [
					"name" => $drive['name'],
					"checked" => true,
				],
			];
		}

		else {
			// If there is a lineage (children), add them to the drive
			$lineage = [
				json_encode($drive_key) => [
					"name" => $drive['name'],
					"children" => $lineage[$folder_id]['children'],
				],
			];
		}

		# Get all the sites
		$sites = $this->getSites();

		# Add the lineage to the relevant site
		foreach($sites as $site_key => $site_name){
			$site_key = json_decode($site_key, true);

			if(strpos($site_key['site_id'], $drive_key['site_id']) !== false){
				$sites[json_encode($site_key)]['children'] = $lineage;
				break;
			}
		}

		# Return all sites (so that the tree can be built)
		return $sites;
	}

	private function getChildren(string $key): ?array
	{
		if(str::isJson($key)){
			$key = json_decode($key, true);
			switch($key['type']) {
			case "site":
				# Sites have drives
				return $this->getDrives($key['site_id']);
			case "drive":
				# Drives have items, some of which could be folders
				return $this->getFolderItems($key['site_id'], $key['drive_id']);
			case "item":
				# Items that are folders have children that could be folders
				return $this->getItems($key['site_id'], $key['drive_id'], $key['item_id']);
			}
		}

		else if($key == "root"){
			# The root has sites
			return $this->getSites();
		}
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
	private function throwError(\Exception $e, ?string $context = NULL, ?string $endpoint = NULL): void
	{
		# The best way of getting error responses
		if(method_exists($e, "getResponse") && method_exists($e->getResponse(), "getBody")){
			$error_code = $e->getCode();

			if(method_exists($e->getResponse()->getBody(), "getContents")){
				$error_json = $e->getResponse()->getBody()->getContents();
			}
			else {
				$error_json = $e->getResponse()->getBody();
			}

			$error_array = json_decode($error_json, true);
			$error_message = $error_array['error']['message'];
		}

		# A decent fallback
		else if($error = $e->getMessage() && $pos = strpos($error, "{")){
			$message = substr($error, 0, $pos);
			$error_array = json_decode(substr($error, $pos), true);
			$error_code = $error_array['error']['code'];
			$error_message = "{$message}: {$error_array['error']['message']}";
		}

		# If everything else fails
		else {
			$error_code = $e->getCode() ?: "Got an";
			$error_message = $e->getMessage();
		}

		if($context){
			$context = str_replace("%20", " ", $context);
			// Replace %20 with space, or else sprintf will interpret it as a placeholder
			$narrative = sprintf($context, $error_code, $error_message);
		}

		else {
			$narrative = $error_message ?: $error;
		}

		$narrative .= $endpoint ? " ({$endpoint})" : NULL;

		Log::getInstance()->error([
			"display" => false,
			"title" => "OneDrive OAuth2 error",
			"message" => $narrative,
			"trace" => $error,
		]);

		throw new \Exception($narrative);
	}
}