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
class OneDrive extends \App\Common\OAuth2\Prototype implements \App\Common\OAuth2\ProviderInterface
{
	public function __construct(array $oauth_token)
	{
		OAuth2Handler::ensureTokenIsFresh($oauth_token);
		$this->graph = new \Microsoft\Graph\Graph();
		$this->graph->setAccessToken($oauth_token['token']);
	}

	/**
	 * @inheritDoc
	 */
	public static function getOAuth2ProviderObject(): object
	{
		$provider = new \TheNetworg\OAuth2\Client\Provider\Azure([
			'clientId' => $_ENV['microsoft_graph_client_id'],
			'clientSecret' => $_ENV['microsoft_graph_client_secret'],
			'redirectUri' => "https://app.{$_ENV['domain']}/oauth2.php",
			"prompt" => "consent",
			'scopes' => [
				"https://graph.microsoft.com/Files.ReadWrite.All",
				'offline_access',
			],
			'defaultEndPointVersion' => '2.0'
		]);

		// Set to use v2 API
		$provider->defaultEndPointVersion = \TheNetworg\OAuth2\Client\Provider\Azure::ENDPOINT_VERSION_2_0;

		return $provider;
	}

	/**
	 * @inheritDoc
	 */
	public function createFolder(string $folder_name, ?string $parent_folder_id = "root"): ?string
	{
		$folder = new \Microsoft\Graph\Model\Folder([
			"name" => $folder_name,
			"folder" => []
		]);

		try {
			$response = $this->graph->setApiVersion("v1.0")
				->createRequest("POST", "/drives/me/items/{$parent_folder_id}/children")
				->addHeaders([
					"Content-Type" => "application/json"
				])
				->attachBody(json_encode([
					"name" => $folder_name,
					"folder" => []
				], JSON_FORCE_OBJECT))
				->execute();

			$response_array = $response->getBody();
		}

		catch(\Exception $e){
			$this->throwError($e, "%s error creating {$folder_name} folder in parent folder ID {$parent_folder_id}: %s");
		}

		return $response_array['id'];
	}

	/**
	 * @inheritDoc
	 */
	public function uploadFile(array $file, ?string $parent_folder_id = "root"): ?string
	{

		try {
			$response = $this->graph->setApiVersion("v1.0")
				->createRequest("PUT", "/drives/me/items/{$parent_folder_id}:/{$file['name']}:/content")
				->addHeaders([
					"Content-Type" => $file['type']
				])
				->upload($file['tmp_name']);

			$response_array = $response->getBody();
		}

		catch(\Exception $e){
			$this->throwError($e, "%s error when uploading {$file['name']} into the folder ID {$parent_folder_id}: %s");
		}

		return $response_array['id'];
	}

	/**
	 * @inheritDoc
	 * @link https://docs.microsoft.com/en-us/graph/query-parameters
	 */
	public function folderExists(string $folder_name, ?string $parent_folder_id = "root"): ?string
	{
		# Escape single quotes
		$folder_name = str_replace("'", "\'", $folder_name);

		try {
			$response = $this->graph->setApiVersion("v1.0")
				->createRequest("GET", "/drives/me/items/{$parent_folder_id}/children?\$filter=(name eq '{$folder_name}')")
				->execute();

			$response_array = $response->getBody();
		}

		catch(\Exception $e){
			$this->throwError($e, "%s error when looking for the {$folder_name} folder: %s");
		}


		if(!$response_array['value']){
			return NULL;
		}

		foreach($response_array['value'] as $item){
			# We're only interested in folders
			if(!$item['folder']){
				continue;
			}
			$matches[$item['id']] = $item['name'];
		}

		# Return the key of the first matching folder
		return array_key_first($matches);
	}

	/**
	 * @inheritDoc
	 * @link https://docs.microsoft.com/en-us/graph/query-parameters
	 */
	public function fileExists(string $file_name, ?string $parent_folder_id = "root"): ?array
	{
		# Escape single quotes
		$file_name = str_replace("'", "\'", $file_name);

		try {
			$response = $this->graph->setApiVersion("v1.0")
				->createRequest("GET", "/drives/me/items/{$parent_folder_id}/children?\$filter=(name eq '{$file_name}')")
				->execute();

			$response_array = $response->getBody();
		}

		catch(\Exception $e){
			$this->throwError($e, "%s error when looking for the {$folder_name} folder: %s");
		}

		if(!$response_array['value']){
			return NULL;
		}

		foreach($response_array['value'] as $file){
			$matches[] = [
				"id" => $file['id'],
				"name" => $file['name'],
				"parents" => $file['parentReference']['id'],
				"size" => $file['size'],
				//OneDrive doesn't give MD5, so we're going to use size instead
			];
		}

		# Return the first matching file
		return reset($matches);
	}

	/**
	 * @inheritDoc
	 */
	public function getFolderName(?string $folder_id): ?string
	{
		try{
			$response = $this->graph->setApiVersion("v1.0")
				->createRequest("GET", "/drives/me/items/{$folder_id}")
				->execute();

			$response_array = $response->getBody();
		}

		catch(\Exception $e) {
			$response = json_decode($e->getResponse()->getBody()->getContents(), true);
			if($response['error']['code'] = "itemNotFound"){
				Log::getInstance()->warning([
					"title" => "Destination folder removed",
					"message" => "The destination folder on your Microsoft OneDrive connection cannot be found.
					Please choose another destination folder. Otherwise the connection will not work."
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
	public function getFolders(string $parent_id, ?string $folder_id = "root"): ?array
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
				$response = $this->graph->setApiVersion("v1.0")
					->createRequest("GET", "/drives/me/items/{$id}")
					->execute();

				$response_array = $response->getBody();

				# If this is the root
				if($response_array['root']){
					# Set the root folder as true
					$folders[$parent_id]['checked'] = true;
					# And we're done
					return $folders;
				}

				# Get the parent ID
				$id = $response_array['parentReference']['id'];

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
			$this->throwError($e, "%s error when loading folders: %s");
		}

		return $folders;
	}

	private function getChildren(string $parent_id): ?array
	{
		try {
			$response = $this->graph->createCollectionRequest("GET", "/drives/me/items/{$parent_id}/children")
				->setReturnType(\Microsoft\Graph\Model\DriveItem::class);

			# Get the first pace
			$docs = $response->getPage();

			# Get any subsequent pages
			while (!$response->isEnd()) {
				$docs = array_merge($docs, $response->getPage());
			}

			foreach($docs as $doc){
				# We're only interested in folders
				if(!$doc->getFolder()){
					continue;
				}
				$children[$doc->getId()] = [
					"name" => $doc->getName()
				];
			}
		}

		catch(\Exception $e) {
			$this->throwError($e, "%s error when loading the child folders for folder ID {$parent_id}: %s");
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
		if($pos = strpos($error, "{")){
			$message = substr($error, 0, $pos);
			$error_array = json_decode(substr($error, $pos), true);
			$error_code = $error_array['error']['code'];
			$error_message = $error_array['error']['message'];
		}

		if($context){
			$narrative = sprintf($context, $error_code, "{$message}: {$error_message}");
		}

		else {
			$narrative = $error_message ?: $error;
		}

		Log::getInstance()->error([
			"display" => false,
			"title" => "OneDrive OAuth2 error",
			"message" => $narrative,
			"trace" => $error
		]);

		throw new \Exception($narrative);
	}
}