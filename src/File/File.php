<?php


namespace App\Common;


use App\Common\Exception\BadRequest;

/**
 * Class File
 *
 * Handles generic file upload issues.
 *
 * @package App\Common
 */
class File {
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
	 * @throws \Exception
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
			throw new \Exception("Unable to move uploaded file. Please try uploading again.");
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
	 * @throws \Exception
	 */
	private static function checkUpload(?string $key = "file"): void
	{
		if(!is_array($_FILES[$key])){
			throw new \Exception("No file was uploaded or received. The <code>\$_FILES</code> array is empty.");
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
			throw new \Exception("{$_FILES[$key]['name']} upload failed. {$message}");
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
	 * @throws \Exception
	 */
	public static function getMatchingMimeType($mime_type, ?string $key = "file"): string
	{
		if(is_string($mime_type)){
			$mime_type = [$mime_type];
		}
		else if(!is_array($mime_type)){
			throw new \Exception("The list of unwanted filetypes must be either a string or an array.");
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
}