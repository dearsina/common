<?php


namespace App\Common\Geolocation;



use App\Common\str;
use App\Email\Email;
use Exception;

class Geolocation extends \App\Common\Prototype {
	/**
	 * The official IP data provider.
	 */
//	const PROVIDER = "ipstack";
	//This provider only has a quote of 100/month

	const PROVIDER = "ipdata";
	//This provider has a quota of 1,500/day

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
	public static function get(?string $ip = NULL): ?array
	{
		$geolocation = new Geolocation();
		return $geolocation->getGeolocation($ip);
	}

	/**
	 * Returns either the remote address IP,
	 * the global IP, or nothing.
	 *
	 * @return string|null
	 */
	public static function getIp(): ?string
	{
		# If a requester IP is set, use it
		if($_SERVER['REMOTE_ADDR']){
			return $_SERVER['REMOTE_ADDR'];
		}

		# Otherwise, use the global ID
		global $ip;
		return $ip;
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
	public function getGeolocation(?string $ip = NULL): ?array
	{
		if(!$ip){
			//if an IP isn't explicitly given, use the requester IP
			$ip = Geolocation::getIp();
		}

		# Without an IP, we can't get geolocation
		if(!$ip){
			$superglobals['$_REQUEST'] = $_REQUEST;
			foreach($_SERVER as $key => $val){
				if($_ENV[$key]){
					continue;
				}
				$superglobals['$_SERVER'][$key] = $val;
			}
			foreach($_SESSION as $key => $val){
				if(in_array($key, ["query", "cached_queries"])){
					continue;
				}
				$superglobals['$_SESSION'][$key] = $val;
			}

			Email::notifyAdmins([
				"subject" => "No IP address given",
				"body" => "Could not run a geolocation check because no IP address was provided.<br><pre>".str::backtrace(true)."</pre>",
				"backtrace" => json_encode($superglobals)
			]);
			return NULL;
		}

		# Most of the time, the data already exists
		if($geolocation = $this->info([
			"rel_table" => "geolocation",
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
			Email::notifyAdmins([
				"subject" => "Geolocation error",
				"body" => "Could not run a geolocation got the following error: {$e->getMessage()}",
			]);
			return NULL;
		}

		# Get the content as a flattened array to insert as a new row
		$array = json_decode($response->getBody()->getContents(), true);

		# Remove location data (too much data for now)
		unset($array['location']);

		# Flatten (don't really need this, as the data *should* be flat already)
		$set = str::flatten($array);

		# Set the type based on the length if it's not given by the API
		if(!$set['type']){
			$set['type'] = strlen($ip) <= 15 ? "ipv4" : "ipv6";
		}

		$this->sql->insert([
			"table" => "geolocation",
			"set" => $set,
			"grow" => true,
		]);

		# Try again, this time the data will have been loaded
		return $this->getGeolocation($ip);
	}

	/**
	 * An array of lists of VPN and CoLo ASNs.
	 * Each list must have a URL and a separator.
	 */
	const ASN_LISTS = [[
		"url" => "https://raw.githubusercontent.com/X4BNet/lists_vpn/main/input/datacenter/ASN.txt",
		"separator" => " # "
	],[
		"url" => "https://raw.githubusercontent.com/X4BNet/lists_vpn/main/input/vpn/ASN.txt",
		"separator" => " # "
	]];

	/**
	 * Loads a VPN/CoLo ASN list into the ASN table.
	 * Is used to identify IP addresses that are probably
	 * from VPNs.
	 *
	 * @return bool
	 */
	public function loadAsnList(): bool
	{
		foreach(self::ASN_LISTS as $asn_list){
			# Download the list
			if(($asn_list_string = file_get_contents($asn_list['url'])) === false){
				continue;
			}

			# Break the list into rows
			$asn_list_rows = str::explode(["\r\n", "\r", "\n"], $asn_list_string);

			# For each row, break it into columns, and load the array
			foreach($asn_list_rows as $row){
				# Break up the row by the separator
				$row = explode($asn_list['separator'], $row);

				# If the row doesn't have an ASN, (empty row), forget it
				if(!$asn = $row[0]){
					continue;
				}

				# Ensure the ASNs are prefixed with "AS"
				if($asn == preg_replace("/[^\d]/", "", $asn)){
					//if the ASN is just the numbers

					# Prefix the ASN with "AS"
					$asn = "AS{$asn}";
				}

				# Load them by ASN, ensure
				$asns[$asn] = [
					"asn" => $asn,
					"name" => $row[1],
				];
			}
		}

		if(!$asns){
			$this->log->error([
				"title" => "Unable to download ASN lists",
				"message" => "Unable to load ASNs from the ".str::pluralise_if(self::ASN_LISTS, "ASN list", true)."."
			]);
			return false;
		}

		# Sort the ASNs by their ASN
		ksort($asns);

		# Truncate the ASN table (but only if it exists)
		if($this->sql->tableExists("app", "asn")){
			$this->sql->run("TRUNCATE TABLE `app`.`asn`;");
		}

		# Load *all* the rows
		$this->sql->insert([
			"table" => "asn",
			"set" => array_values($asns),
			"grow" => true,
		]);

		$this->log->success([
			"title" => "Loaded ASN lists successfully",
			"message" => "Loaded ".count($asns)." unique ASNs successfully."
		]);

		return true;
	}
}