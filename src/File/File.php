<?php


namespace App\Common\File;


use App\Common\Exception\BadRequest;
use App\Common\str;
use Exception;

/**
 * Class File
 *
 * Handles generic file upload issues.
 *
 * @package App\Common
 */
class File {
	/**
	 * The maximum number of megabytes a file can be before
	 * it is shrunk.
	 */
	const MAX_FILESIZE_MB = 4;

	/**
	 * The max JPG quality a file can be compressed down to.
	 */
	const MAX_JPG_QUALITY = 50;

	/**
	 * Takes the superglobal $_FILES array and converts
	 * it into a local variable, copies the superglobal
	 * file to a local version, because the superglobal
	 * version will expire as soon as this thread
	 * completes,
	 * and as file handling threads are often farmed out
	 * the file needs to stay alive until all the work
	 * has been completed.
	 *
	 * Returns the following array:
	 * <code>
	 * array(6) {
	 *    ["name"]=>
	 *    string(18) "filename.jpg"
	 *    ["ext"]=>
	 *    string(3) "jpg"
	 *    ["type"]=>
	 *    string(10) "image/jpeg"
	 *    ["tmp_name"]=>
	 *    string(49) "/var/www/tmp/8b3f2443-18ac-4530-b92f-1d6f4633b0ac"
	 *    ["error"]=>
	 *    int(0)
	 *    ["size"]=>
	 *    int(191463)
	 *    ["md5"]=>
	 *    string(32) "da354984ecc3265e4bcf542593f51771"
	 * }
	 * </code>
	 *
	 * @param string|null $key             In case the
	 *                                     $_FILES array
	 *                                     key is not
	 *                                     "file"
	 * @param bool|null   $no_gifs_allowed if set, will not
	 *                                     allow image/gif
	 *                                     files
	 *
	 * @return array
	 * @throws Exception
	 */
	public static function handleUpload(?string $key = "file", ?bool $no_gifs_allowed = NULL): array
	{
		# Ensure upload was successful
		self::checkUpload($key);

		# No GIFs allowed (optional)
		if($no_gifs_allowed){
			//if no GIFs are allowed
			if($file_name = File::getMatchingMimeType("image/gif", $key)){
				//if the file is a GIF
				throw new BadRequest("While almost all image formats are accepted, your file <code>{$file_name}</code> is a GIF and GIFs are not accepted. Please use JPG or PNG, or upload a PDF.");
			}
		}

		# Generate an arbitrary filename
		$tmp_file_name = str::uuid();

		# Copy the superglobal to a local variable
		$file = $_FILES[$key];
		$tmp_dir = sys_get_temp_dir();
		$tmp_name = "{$tmp_dir}/{$tmp_file_name}";

		# Move the temp file to a semi-permanent location (so that we can hand over the file to a different php thread)
		if(!move_uploaded_file($_FILES[$key]['tmp_name'], $tmp_name)){
			throw new Exception("Unable to move uploaded file. Please try uploading again.");
		}

		# Record the MD5 hash of the file
		$file['md5'] = md5_file($tmp_name);

		# Get the file extension
		$file['ext'] = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
		//the extension is stored in all lowercase only

		# Update the tmp_name to the new tmp_name
		$file['tmp_name'] = $tmp_name;

		return $file;
	}


	/**
	 * Checks to see if there are any errors with the
	 * upload.
	 *
	 * @throws Exception
	 */
	private static function checkUpload(?string $key = "file"): void
	{
		if(!is_array($_FILES[$key])){
			throw new Exception("No file was uploaded or received. The <code>\$_FILES</code> array is empty.");
		}

		if(is_array($_FILES[$key]['error'])){
			foreach($_FILES[$key]['error'] as $e){
				if($e){
					$error = $e;
					break;
				}
			}
		}
		else if($_FILES[$key]['error']){
			$error = $_FILES[$key]['error'];
		}

		if(!$error){
			//If no error is found
			return;
		}

		switch($error) {
		case UPLOAD_ERR_INI_SIZE:
			$message = "The uploaded file exceeds the upload_max_filesize directive in php.ini.";
			break;
		case UPLOAD_ERR_FORM_SIZE:
			$message = "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.";
			break;
		case UPLOAD_ERR_PARTIAL:
			$message = "The uploaded file was only partially uploaded.";
			break;
		case UPLOAD_ERR_NO_FILE:
			$message = "No file was uploaded.";
			break;
		case UPLOAD_ERR_NO_TMP_DIR:
			$message = "Missing a temporary folder.";
			break;
		case UPLOAD_ERR_CANT_WRITE:
			$message = "Failed to write file to disk.";
			break;
		case UPLOAD_ERR_EXTENSION:
			$message = "File upload stopped by extension.";
			break;
		default:
			$message = "Unknown upload error.";
			break;
		}

		if($message){
			throw new Exception("{$_FILES[$key]['name']} upload failed. {$message}");
		}
	}

	/**
	 * Given one or more mime types, will check to see if
	 * any of the uploaded files belong to that mine type.
	 * If at least one file does, will return the file
	 * name.
	 *
	 * @param             $mime_type
	 * @param string|null $key
	 *
	 * @return string
	 * @throws Exception
	 */
	public static function getMatchingMimeType($mime_type, ?string $key = "file"): string
	{
		if(is_string($mime_type)){
			$mime_type = [$mime_type];
		}
		else if(!is_array($mime_type)){
			throw new Exception("The list of unwanted filetypes must be either a string or an array.");
		}

		if(str::isNumericArray($_FILES[$key]['tmp_name'])){
			//if multiple files have been uploaded at once
			foreach($_FILES[$key]['tmp_name'] as $id => $tmp_name){
				if(in_array(mime_content_type($tmp_name), $mime_type)){
					return $_FILES[$key]['name'][$id];
				}
			}
		}

		else {
			if(in_array(mime_content_type($_FILES[$key]['tmp_name']), $mime_type)){
				return $_FILES[$key]['name'];
			}
		}

		return false;
	}

	/**
	 * Once a file is uploaded the cloud, run this method
	 * to remove any local temp copies of it.
	 * This method can be run multiple times.
	 *
	 * @param array $file
	 */
	public static function deleteLocalTemp(array $file): void
	{
		# Delete the local copy of the file (as it's now in the cloud)
		if(file_exists($file['tmp_name'])){
			//if the file exists of course
			unlink($file['tmp_name']);
		}

		# Delete the JPG pages (if doc is a PDF)
		if($file['pdf_info']['pages']){
			//if the doc has multiple pages
			for($page = 1; $page <= $file['pdf_info']['pages']; $page++){
				$tmp_name = $file['tmp_name'] . "-{$page}";
				if(file_exists($tmp_name)){
					//we have this in place because sometimes the file doesn't exist. Not sure why.
					unlink($tmp_name);
				}
			}
		}
	}

	/**
	 * Shrinks image files that are bigger than the max number of set megabytes.
	 * Will first compress down to the max quality, and if that's not enough,
	 * will shrink the size of the image proportionately.
	 *
	 * @param array $file
	 *
	 * @throws \ImagickException
	 */
	public static function correctFileSize(array &$file, ?float $max_filesize_mb = NULL): void
	{
		if($file['pdf_info']){
			//if the file is PDF
			return;
			//do nothing
		}

		# Allow for custom overrides of the max file size
		$max_filesize_mb = $max_filesize_mb ?: self::MAX_FILESIZE_MB;

		if($file['size'] / 1048576 <= $max_filesize_mb){
			//if the filesize NOT bigger than the max number of allowed megabytes
			return;
			//do nothing
		}

		# Open ImageMagik
		$image = new \Imagick();

		# Read the original file (as uploaded by the client)
		$image->readImage($file['tmp_name']);

		# Set new (reduced) image quality
		$image->setImageCompression(\Imagick::COMPRESSION_JPEG);
		$image->setImageCompressionQuality(self::MAX_JPG_QUALITY);
		$image->setImageFormat('jpg');
		$image->stripImage();

		# Save it
		$image->writeImage($file['tmp_name']);
		$image->clear();

		# Read the new (compressed) image
		$image->readImage($file['tmp_name']);

		# Get the new file size, if it's NOW small enough, stop
		if($image->getImageLength() / 1048576 <= $max_filesize_mb){
			$image->clear();
			return;
		}

		# If the image is still too big, calculate the proportional scale
		$size_difference = $image->getImageLength() / (1048576 * $max_filesize_mb);

		# Reset the resolution
		$image->setImageResolution(72, 72);
		$image->resampleImage(72, 72, \Imagick::FILTER_UNDEFINED, 1);

		# Get the dimensions
		$geometry = $image->getImageGeometry();

		# Scale the image down proportionately (assuming a direct relationship between image and file size)
		$new_width = floor($geometry['width'] / $size_difference);
		$new_height = floor($geometry['height'] / $size_difference);
		$image->scaleImage($new_width, $new_height);

		# Save it
		$image->writeImage($file['tmp_name']);

		# And we're done
		$image->clear();
	}
}