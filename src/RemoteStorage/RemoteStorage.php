<?php

namespace App\Common\RemoteStorage;

use API\Aws\S3\S3;
use API\Microsoft\Azure\Azure;
use App\Common\Prototype;
use App\Subscription\Subscription;

/**
 * The middleware for remote storage.
 */
class RemoteStorage extends Prototype {

    //The value is based the region value pulled from Cloudian
    private const S3_LOCATIONS = [
        'my', // Malaysia
        'sa', // Saudi Arabia
        'tr', // Turkey
        'vn', // Vietnam
    ];

    private string $location;

    private RemoteStorage $storage;

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
	 * @return Azure|S3|RemoteStorage|object
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
	 * @return Azure|S3
	 */
	private static function getProviderObject(?string $location)
	{
        // Checks location against the array key because the value stored is the region code as per AWS and Cloudian
        if (in_array($location, self::S3_LOCATIONS)) {
            return new S3($location);
        }

		return new Azure($location);
	}

    /**
     * @param string|null $location
     * @return bool
     */
    public function isLocation(?string $location): bool
    {
        if(!$location){
            $location = Subscription::DEFAULT_LOCATION;
        }

        return $this->location == strtoupper($location);
    }

    /**
     * Ensures that location is always set to the correct one for the subscription
     *
     * @param string|null $location
     * @return void
     */
    private function setLocation(?string $location): void
    {
        if(!$location){
            $location = Subscription::DEFAULT_LOCATION;
        }

        $this->location = strtolower($location);
    }

    /**
     * @return string
     */
    public function getLocation(): string
    {
        return $this->location;
    }

}