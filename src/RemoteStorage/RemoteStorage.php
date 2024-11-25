<?php

namespace App\Common\RemoteStorage;

use API\Microsoft\Azure\Azure;
use App\Common\Prototype;

/**
 * The middleware for remote storage.
 */
class RemoteStorage extends Prototype {
	/**
	 * Creates a remote storage instance.
	 *
	 * The location is expected to a ISO 3166-1 alpha-2 country code.
	 * If no location is passed, it will default to "za".
	 *
	 * The location will determine which Azure storage account to connect to.
	 *
	 * By passing an existing storage instance, you can load a new instance
	 * only if the location passed is different from the current instance's location.
	 * Otherwise, it will return the current instance.
	 *
	 * @param string|null $location ISO 3166-1 alpha-2 country code
	 * @param object|null $storage An existing storage instance
	 *
	 * @return Azure|mixed
	 */
	public static function create(?string $location = NULL, $storage = NULL)
	{
		# If an instance wasn't passed, create a new one
		if(!$storage){
			return RemoteStorage::getProviderObject($location);
		}

		# If no location was passed, return the current instance
		if(!$location){
			return $storage;
		}

		# Compare the current instance's location with the passed location
		if($storage->isLocation($location)){
			// If they're the same, return the current instance
			return $storage;
		}

		# If they're different, create a new instance
		return RemoteStorage::getProviderObject($location);
	}

	/**
	 * Returns the appropriate provider object,
	 * depending on the location passed.
	 *
	 * If no location is passed, it will default to "za".
	 *
	 * @param string|null $location
	 *
	 * @return Azure
	 */
	private static function getProviderObject(?string $location)
	{
		return new Azure($location);
	}
}