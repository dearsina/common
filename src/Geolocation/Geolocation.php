<?php


namespace App\Common\Geolocation;



use App\Common\str;
use Exception;

class Geolocation extends \App\Common\Prototype {
	/**
	 * The official IP data provider.
	 */
	const PROVIDER = "ipstack";

	/**
	 * Each provider's particulars to feed in to the Guzzle request.
	 *
	 * @var array
	 */
	private array $providers = [
		# Provides standard amounts of data
		"ipstack" => [
			"base_uri" => "http://api.ipstack.com",
			"query" => [
				"access_key" => "ipstack_access_key"
			]
		],

		# Provides slightly more data
		"ipdata" => [
			"base_uri" => "https://api.ipdata.co",
			"query" => [
				"api-key" => "ipdata_api_key"
			]
		],
	];

	/**
	 * Given an IP address,
	 * records and returns geolocation details
	 * for the given IP. If no IP is give,
	 * will use the current user's IP.
	 *
	 * Is a shortcut for the OOP method.
	 *
	 * @param string|null $ip
	 *
	 * @return array|null
	 */
	public static function get(string $ip = NULL): ?array
	{
		$geolocation = new Geolocation();
		return $geolocation->getGeolocation($ip);
	}

	/**
	 * Static method is probably faster/better. Use:
	 * <code>
	 * Geolocation::get($ip);
	 * </code>
	 *
	 * @param string|null $ip
	 *
	 * @return array|null
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function getGeolocation(string $ip = NULL): ?array
	{
		$ip = $ip ?: $_SERVER['REMOTE_ADDR'];
		//if an IP isn't explicitly given, use the requester's IP

		# Most of the time, the data already exists
		if($geolocation = $this->sql->select([
			"table" => "geolocation",
			"where" => [
				"ip" => $ip
			],
			"limit" => 1
		])){
			return $geolocation;
		}

		# Load the provider
		$provider = $this->providers[self::PROVIDER];

		# The first query variable is (assumed to be) the API key name
		$api_key_name = reset($provider['query']);

		if(!$_ENV[$api_key_name]){
			//if a key hasn't been set, no data can be gathered
			throw new Exception("No API key found for ".self::PROVIDER.". Ensure the <code>{$api_key_name}</code> environmental variable is populated.");
		}

		$client = new \GuzzleHttp\Client([
			"base_uri" => $provider['base_uri']
		]);

		try {
			$response = $client->request("GET", "/{$ip}", [
				"query" => [
					array_key_first($provider['query']) => $_ENV[$api_key_name],
				]
			]);
		}
		catch (Exception $e) {
			//Catch errors
			$this->log->error([
				"title" => "Geolocation error",
				"message" => $e->getMessage()
			]);
			return NULL;
		}

		# Get the content as a flattened array to insert as a new row
		$array = json_decode($response->getBody()->getContents(), true);

		# Remove location data (too much data for now)
		unset($array['location']);

		# Flatten (don't really need this, as the data *should* be flat already)
		$set = str::flatten($array);

		$this->sql->insert([
			"table" => "geolocation",
			"set" => $set,
			"grow" => true,
		]);

		# Try again, this time the data will have been loaded
		return $this->getGeolocation($ip);
	}
}