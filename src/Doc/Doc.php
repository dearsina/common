<?php

namespace App\Common\Doc;

use App\Common\Exception\BadRequest;
use App\Common\Log;
use App\Common\str;
use Pelago\Emogrifier\CssInliner;

/**
 * Generic document handling methods.
 */
class Doc extends \App\Common\Prototype {

	/**
	 * Checks to see if a file is a PDF,
	 * and if the PDF is passport protected.
	 *
	 * @param array $file
	 *
	 * @return bool
	 */
	public static function isPasswordProtected(array $file): bool
	{
		if(mime_content_type($file['tmp_name']) != "application/pdf"){
			//If the file is NOT a PDF, do nothing
			return false;
		}

		# Confusingly, will return 2 if there is NO password, and 0, if there IS one
		$output = (int)trim(shell_exec("qpdf --requires-password \"{$file['tmp_name']}\" ; echo \$?"));

		return $output === 0;
		// Returns TRUE if the file is password protected
	}

	/**
	 * Decrypts a given (PDF) file with the given password.
	 * Will return an error message (if there is one), or NULL
	 * if everything went swimmingly.
	 *
	 * Will keep the encrypted file MD5 so that if the encrypted
	 * file is accidentally re-uploaded, the system will detect it
	 * before decrypting it.
	 *
	 * <code>
	 * if($error = Doc::decryptPassword($file, $vars['password'])){
	 *    //Do something with the error message
	 * }
	 * </code>
	 *
	 * @param array  $file
	 * @param string $password
	 *
	 * @return string|null Will return an error string or NULL on success
	 */
	public static function decryptPassword(array $file, string $password): ?string
	{
		# Escape double quotation marks
		$password = str_replace('"', '\\"', $password);

		# The command to run
		$command = "qpdf --password=\"{$password}\" --decrypt --replace-input {$file['tmp_name']} 2>&1";

		# If the command has an output (that's bad news)
		$output = shell_exec($command);

		# Return the last bit of the output, which is generally the error message
		if($output){
			$error = explode(":", $output);
			return trim(end($error));
		}

		return NULL;
	}

	/**
	 * Checks to see if a PDF is encrypted in any way.
	 * Not really used at the moment, but kept just in case.
	 *
	 * @param array $file
	 *
	 * @return bool
	 */
	public static function isPdfEncrypted(array $file): bool
	{
		if(mime_content_type($file['tmp_name']) != "application/pdf"){
			//If the file is NOT a PDF, do nothing
			return false;
		}

		# Confusingly, will return 2 if there is NO password, and 0, if there IS one
		$output = shell_exec("qpdf --show-encryption {$file['tmp_name']} 2>&1");

		if($output == "File is not encrypted"){
			return false;
		}

		return true;
	}

	/**
	 * Checks to see if a file is a HEIC file.
	 * Does that by examining the type and/or the name extension.
	 * Will also check the mime type if the file type is not set to HEIC.
	 * The mime type check is expensive, so it's optional.
	 *
	 * @param array     $file
	 * @param bool|null $check_mime_type If set to true, will check the mime type of the file as a last resort
	 *
	 * @return bool If the file is a HEIC, will return true, otherwise false.
	 */
	public static function isHeic(array $file, ?bool $check_mime_type = NULL): bool
	{
		if($file['type'] == "image/heic"){
			return true;
		}

		if($file['type'] == "application/octet-stream" && strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) == "heic"){
			return true;
		}

		if($check_mime_type && mime_content_type($file['tmp_name']) == "image/heic"){
			// If the file is a HEIC, but the file type is not set to HEIC
			return true;
		}

		return false;
	}

	/**
	 * If the uploaded file is a PDF, will try to open the
	 * file and will add the following data points (and
	 * others) to the $file array under the `pdf_info` key
	 * if the file is readable. Otherwise, will error out.
	 *
	 * title:          test1.pdf
	 * author:         John Smith
	 * creator:        PScript5.dll Version 5.2.2
	 * producer:       Acrobat Distiller 9.2.0 (Windows)
	 * creation_date:  01/09/13 19:46:57
	 * mod_date:       01/09/13 19:46:57
	 * tagged:         yes
	 * form:           none
	 * pages:          13
	 * encrypted:      no
	 * page_size:      2384 x 3370 pts (A0)
	 * file_size:      17569259 bytes
	 * optimized:      yes
	 * pdf_version:    1.6
	 *
	 * @link https://web.archive.org/web/20170706043824/http://linuxcommand.org:80/man_pages/pdfinfo1.html
	 *
	 * @param array $file
	 */
	public static function addPDFMetadata(array &$file, ?bool $silent = NULL): void
	{
		if(mime_content_type($file['tmp_name']) != "application/pdf"){
			//If the file is NOT a PDF, do nothing
			return;
		}

		# Can't extract data if the file is password protected
		if(\App\Doc\Doc::isPasswordProtected($file)){
			$file['pdf_info'] = [
				'encrypted' => 'yes',
			];
			return;
		}

		# Run the pdfinfo command
		exec("pdfinfo '{$file['tmp_name']}'", $output, $return_var);

		# Ensure the PDF was readable (and error out if not)
		switch($return_var) {
		case 1: # Error opening a PDF file.
			$error = "There was an error opening your PDF file <code>{$file['name']}</code>. Please ensure it is a valid PDF file and try again.";
			break;
		case 2: # Error opening an output file.
			$error = "There was an error opening an output file for your PDF file <code>{$file['name']}</code>. Please try again.";
			break;
		case 3: # Error related to PDF permissions.
			$error = "There was a permission error when opening your PDF file <code>{$file['name']}</code>. Please remove any passwords this PDF may have and try again.";
			break;
		case 99: # Other error.
			$error = "There was an unknown error opening your PDF file <code>{$file['name']}</code>. Please ensure it is a valid PDF file and try again.";
			break;
		default: # No error reported
			break;
		}

		# Handle errors, depending on whether or not we're in silent mode
		if($error){
			if(!$silent){
				throw new BadRequest($error);
			}
			$file['pdf_info'] = [
				'error' => $error,
				"tmp_name" => $file['tmp_name'],
			];
			return;
		}

		# Add the extracted metadata to the $file array
		foreach($output as $line){
			preg_match("/^([a-z\s]+):\s+(.*)$/ui", $line, $matches);
			if($matches[1]){
				$key = str::camelToSnakeCase($matches[1]);
				switch($key) {
				case 'page_size':
					# Get the exact dimensions in pts and in inches
					$dimensions = array_values(array_filter(preg_split("/[a-z\s]/", $matches[2])));
					if(count($dimensions) >= 2){
						$file['pdf_info']['page_width'] = $dimensions[0];
						$file['pdf_info']['page_height'] = $dimensions[1];
						$file['pdf_info']['page_width_in'] = round($dimensions[0] / 72, 2);
						$file['pdf_info']['page_height_in'] = round($dimensions[1] / 72, 2);
					}
					# Capture the document page format
					if($format = preg_replace("/[^(]+\(([^)]+)\)$/", "$1", $matches[2])){
						$file['format'] = $format;
					}
				default:
					$file['pdf_info'][$key] = $matches[2];
					break;
				}
			}
		}

		# Adds any PDF text into the pdf_info key
		self::setPdfText($file);
	}

	/**
	 * Checks to see if a PDF has text (or if it's just images).
	 * Adds the text to the pdf-info array.
	 *
	 * @return bool Returns true if there is text.
	 * @throws \Exception
	 */
	public static function setPdfText(array &$file): bool
	{
		# File needs to be PDF
		if(!$file['pdf_info']){
			return false;
		}

		# If the file is bigger than 2mb, don't try to extract text
		if($file['size'] > 2000000){
			$file['pdf_info']['text_error'] = "This PDF is too large to extract text from.";
			return false;
		}

		# Password-protected files can't have their text extracted
		if(self::isPasswordProtected($file)){
			$file['pdf_info']['text_error'] = "No data was extracted, because this PDF is password protected.";
			return false;
		}

		# Populate the pdf_info > text key with the text from the PDF
		if(!Doc::setTextFromPdfToTextCommand($file)){
			// If that didn't work either, abort mission
			return false;
		}

		# Filter the text for unfriendly characters
		$file['pdf_info']['text'] = preg_replace('/[^[:print:][:space:]]/', "", $file['pdf_info']['text']);
		// We're removing all non-printable and non-space characters to avoid issues loading the array (as a JSON)

		return (bool)strlen($file['pdf_info']['text']);
	}

	/**
	 * Extract and set the text from a PDF using the pdftotext command.
	 *
	 * Uses a more liberal approach to extract text from a PDF.
	 * Will populate the $file['pdf_info']['text'] variable if successful.
	 * Will return the success of the operation.
	 * If there is an error, it will be added to the $file array.
	 *
	 * @param array $file
	 *
	 * @return bool
	 * @link https://stackoverflow.com/a/75046857/429071
	 */
	private static function setTextFromPdfToTextCommand(array &$file): bool
	{
		# Try extracting text
		$cmd = "pdftotext -nopgbrk \"{$file['tmp_name']}\" -";
		exec($cmd, $output, $result_code);
		// nopgbrk means no page break, so the entire document is extracted as one long string
		// The dash at the end means the output is sent to stdout

		# If there are no errors, set the text
		if($result_code == 0){
			$file['pdf_info']['text'] = implode("\n", $output);
			return true;
		}

		# If there are errors, set the error and return false
		$file['pdf_info']['text_error'] = "When using pdftotext [{$cmd}], the following error [{$result_code}] was encountered: " . implode("\n", $output);
		return false;
	}

	/**
	 * Checks to see if there are any errors with the
	 * upload.
	 *
	 * @throws \Exception
	 */
	public static function checkUpload(?string $key = "file"): void
	{
		if(!is_array($_FILES[$key])){
			throw new \Exception("No file was uploaded or received. The <code>\$_FILES</code> array is empty.");
		}

		if(is_array($_FILES[$key]['error'])){
			foreach($_FILES[$key]['error'] as $i => $e){
				if($e){
					$error = $e;
					break;
				}
			}
		}

		else if($_FILES[$key]['error']){
			$i = NULL;
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
			$name = $i !== NULL ? $_FILES[$key]['name'][$i] : $_FILES[$key]['name'];
			throw new \Exception("{$name} upload failed. {$message}");
		}
	}

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
	public static function handleUpload(?string $key = "file", ?bool $no_gifs_allowed = NULL, ?bool $multiple = NULL): array
	{
		# Ensure upload was successful
		self::checkUpload($key);

		# No GIFs allowed (optional)
		if($no_gifs_allowed){
			//if no GIFs are allowed
			if($file_name = Doc::getMatchingMimeTypeFile("image/gif", $key)){
				//if the file is a GIF
				throw new BadRequest("While almost all image formats are accepted, your file <code>{$file_name}</code> is a GIF and GIFs are not accepted. Please use JPG or PNG, or upload a PDF.");
			}
		}

		# Copy the superglobal to a local variable
		$file = $_FILES[$key];

		if(!is_array($file['tmp_name'])){
			$file['name'] = [$file['name']];
			$file['type'] = [$file['type']];
			$file['tmp_name'] = [$file['tmp_name']];
			$file['size'] = [$file['size']];
		}

		foreach($file['tmp_name'] as $i => $f){
			# Generate an arbitrary filename
			$tmp_file_name = str::uuid();

			$tmp_dir = sys_get_temp_dir();
			$tmp_name = "{$tmp_dir}/{$tmp_file_name}";

			# Move the temp file to a semi-permanent location (so that we can hand over the file to a different php thread)
			if(!move_uploaded_file($file['tmp_name'][$i], $tmp_name)){
				$name = reset($file['name']);
				throw new \Exception("Unable to move uploaded file {$name}. Please try uploading again.");
			}

			if(!$files[$i]['size'] = $file['size'][$i]){
				//If the filesize is zero
				throw new \Exception("The file uploaded ({$file['name'][$i]}) is empty. Please try again.");
			}

			$files[$i]['name'] = $file['name'][$i];
			$files[$i]['type'] = $file['type'][$i];

			# Record the MD5 hash of the file
			$files[$i]['md5'] = md5_file($tmp_name);

			# Get the file extension
			$files[$i]['ext'] = strtolower(pathinfo($file['name'][$i], PATHINFO_EXTENSION));
			//the extension is stored in all lowercase only

			# Get the mime type
			$files[$i]['mime_type'] = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $tmp_name);

			# Update the tmp_name to the new tmp_name
			$files[$i]['tmp_name'] = $tmp_name;
		}

		if($multiple){
			return $files;
		}

		return reset($files);
	}

	/**
	 * At times, the selfie camera will accidentally upload a black image,
	 * when the camera has been turned off, but the "picture" is still taken.
	 * This function will check if the image is all black, and if so,
	 * will (optionally) delete it and return true.
	 *
	 * @param array     $file
	 * @param bool|null $delete
	 *
	 * @return bool
	 */
	public static function pureBlackImageWasUploaded(array $file, ?bool $delete = true): bool
	{
		# This only applies to images, not PDFs, etc
		if(strpos($file['mime_type'], "image") === false){
			return false;
		}

		# This command will return the average color of the image, if it's 0, the image is all black
		$cmd = "convert {$file['tmp_name']} -colorspace gray -format \"%[fx:mean]\n\" info:";

		# If the image is not all black, return false
		if((float)exec($cmd)){
			return false;
		}

		# Remove the tmp file
		if($delete){
			exec("rm {$file['tmp_name']}");
		}

		# The image is all black
		return true;
	}

	/**
	 * Given one or more mime types, will check to see if
	 * any of the uploaded files belong to that mine type.
	 * If at least one file does, will return the file
	 * name.
	 *
	 * @param string|array $mime_type
	 * @param string|null  $key
	 *
	 * @return string|null
	 * @throws \Exception
	 */
	public static function getMatchingMimeTypeFile($mime_type, ?string $key = "file"): ?string
	{
		# Ensure the mime type variable is an array
		if(is_string($mime_type)){
			$mime_type = [$mime_type];
		}
		else if(!is_array($mime_type)){
			throw new \Exception("The list of unwanted filetypes must be either a string or an array.");
		}

		if(str::isNumericArray($_FILES[$key]['tmp_name'])){
			//if multiple files have been uploaded at once
			foreach($_FILES[$key]['tmp_name'] as $id => $tmp_name){
				if(in_array(finfo_file(finfo_open(FILEINFO_MIME_TYPE), $tmp_name), $mime_type)){
					return $_FILES[$key]['name'][$id];
				}
			}
		}

		else {
			# Ensure there *is* one or more tmp files
			if(!file_exists($_FILES[$key]['tmp_name'])){
				return NULL;
			}

			if(in_array(finfo_file(finfo_open(FILEINFO_MIME_TYPE), $_FILES[$key]['tmp_name']), $mime_type)){
				return $_FILES[$key]['name'];
			}
		}

		return NULL;
	}

	public static function isImageOrPdf(?array $file): bool
	{
		$mime_type = $file['mime_type'] ?: $file['type'];

		if($mime_type == "application/pdf"){
			return true;
		}

		if(strpos($mime_type, "image/") === 0){
			return true;
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
			exec("rm {$file['tmp_name']}");
		}

		# Delete the JPG pages (if doc is a PDF)
		if($file['pdf_info']['pages']){
			//if the doc has multiple pages
			for($page = 1; $page <= $file['pdf_info']['pages']; $page++){
				$tmp_name = $file['tmp_name'] . "-{$page}";
				if(file_exists($tmp_name)){
					//we have this in place because sometimes the file doesn't exist. Not sure why.
					exec("rm {$tmp_name}");
				}
			}
		}

		# Delete any originals also
		if($originals = Convert::getOriginals($file)){
			foreach($originals as $method => $method_file){
				if(file_exists($method_file['tmp_name'])){
					//if the file exists of course
					exec("rm {$method_file['tmp_name']}");
				}
			}
		}
	}

	/**
	 * Checks to see if the uploaded file is a font or not.
	 * Can throw errors instead of returning boolean.
	 *
	 * @param array $file
	 *
	 * @return bool
	 * @throws BadRequest
	 */
	public static function fileIsFont(array &$file, ?bool $throw_error = NULL): bool
	{
		# If a WOFF file is passed, convert it and work on the TTF instead
		switch(strtolower($file['ext'])) {
		case 'woff':
		case 'woff2':
			if($ttf = Convert::convertWoffToTtf($file['tmp_name'])){
				# Remove the woff file
				exec("rm {$file['tmp_name']}");

				# Update the file array
				$file = [
					"ext" => "ttf",
					"mime_type" => "application/x-font-ttf",
					"tmp_name" => $ttf,
					"name" => pathinfo($file['name'], PATHINFO_FILENAME) . ".ttf",
					"type" => "application/x-font-ttf",
					"md5" => md5_file($ttf),
				];
				return true;
			}
			if($throw_error){
				throw new BadRequest("The <code>{$file['name']}</code> WOFF file could not be converted.");
			}
			return false;
		}

		$cmd = "otfinfo -a {$file['tmp_name']}";
		exec($cmd, $output, $return_var);
		switch($return_var) {
		case 1: # Error getting font info
			if($throw_error){
				throw new BadRequest("The <code>{$file['name']}</code> file is not a true type (TTF) or open type (OTF) font file.");
			}
			return false;
		default:
			return true;
		}
	}

	/**
	 * Converts aa SVG to a PNG of a given size.
	 * Does not grow the SVG content, more like
	 * just the frame that holds the SVG. To grow
	 * the SVG, use the `enlargeSvg` method.
	 *
	 * @param array      $file
	 * @param float|null $width
	 * @param float|null $height
	 *
	 * @throws \ImagickException
	 */
	public static function convertSvgToPng(array &$file, ?float $width = NULL, ?float $height = NULL): void
	{
		# Set up ImageMagick
		$im = new \Imagick();

		# Read the SVG
		$im->readImageBlob(file_get_contents($file['tmp_name']));

		# Set the image format to be PNG
		$im->setImageFormat("png24");

		# Resize if required
		if($width != NULL && $height != NULL){
			$im->resizeImage($width, $height, \imagick::FILTER_LANCZOS, 1);
		}

		# Grab the SVG tmp_name
		$svg_tmp_name = $file['tmp_name'];

		# Add a ".png" to the tmp filename
		$file['tmp_name'] .= ".png";

		# Update the ext to be png
		$file['ext'] = "png";

		# Update the content type to be image/png
		$file['type'] = "image/png";

		# Write the PNG to the new filename
		$im->writeImage($file['tmp_name']);

		# Close ImageMagick
		$im->clear();
		$im->destroy();

		# Remove the SVG copy
		exec("rm {$svg_tmp_name}");
	}

	/**
	 * Max width/height generally accepted
	 * by AWS/Azure.
	 */
	const MAX_WIDTH_HEIGHT_PX = 3000;

	/**
	 * The max page size in inches.
	 *
	 * @param array $file
	 *
	 * @return void
	 */
	const MAX_PAGE_SIZE_IN = 11;

	/**
	 *  Max page size width/height
	 *  is 11 inches, which is the height
	 *  of an A4 page. At times, PDFs are
	 *  uploaded with larger page sizes.
	 *  This method will rescale the PDF
	 *  to be max 11 inches in
	 *  width/height. This doesn't change
	 *  the content or filesize, but it
	 *  allows for better handling.
	 *
	 * @param array $file
	 *
	 * @return void
	 */
	private static function rescalePdfWidthAndHeight(array &$file): void
	{
		$output = shell_exec("pdfinfo {$file['tmp_name']} | grep 'Page size'");
		if(!preg_match("/Page size:\s+(\d+\.\d+) x (\d+\.\d+)/", $output, $matches)){
			// If the page size can't be found, abort mission
			return;
		}

		$file['width'] = $matches[1] / 72;  // Convert points to inches
		$file['height'] = $matches[2] / 72;  // Convert points to inches

		if($file['width'] <= self::MAX_PAGE_SIZE_IN && $file['height'] <= self::MAX_PAGE_SIZE_IN){
			// If the page size is already within the max size, pencils down
			return;
		}

		$scale_width = self::MAX_PAGE_SIZE_IN / $file['width'];
		$scale_height = self::MAX_PAGE_SIZE_IN / $file['height'];

		$scale_factor = min($scale_width, $scale_height);

		$new_width = intval($dimensions['width'] * $scale_factor * 72);  // Convert back to points
		$new_height = intval($dimensions['height'] * $scale_factor * 72); // Convert back to points

		# Use GhostScript to resize the PDF
		$cmd = "gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dNOPAUSE -dQUIET -dBATCH -sOutputFile={$file['tmp_name']} -g{$new_width}x{$new_height} -r72x72 -c \"<</BeginPage{{$scale_factor} {$scale_factor} scale}>> setpagedevice\" -f {$file['tmp_name']}";

		$output = [];
		str::exec($cmd, $output);

		# Log and execute the command
		$file['cmd'][] = $cmd;
		$file['output'][] = $output;
	}

	/**
	 * Converts the nth page of a PDF to a JPG.
	 *
	 * Ensures the image isn't larger than the
	 * max width/height in px to be used in AWS
	 * Rekognition or Azure's OCR.
	 *
	 * @param array    $file
	 * @param int|null $page_number
	 * @param int|null $resolution
	 * @param int|null $quality
	 */
	public static function convertPdfToJpg(array &$file, ?int $page_number = 1, ?int $resolution = 200, ?int $quality = 50): void
	{
		# Ensure file is PDF
		if($file['type'] != "application/pdf"){
			//if the file isn't a PDF
			return;
		}

		# Write command to convert the PDF to JPG using pdftoppm
		$cmd = "pdftoppm -f {$page_number} -l {$page_number} -jpeg -r {$resolution} -jpegopt quality={$quality} {$file['tmp_name']} {$file['tmp_name']} 2>&1";

		$output = [];

		# Execute the command
		if(!str::exec($cmd, $output)){
			// If there is an issue

			# Rescale the PDF, that will probably solve it
			self::rescalePdfWidthAndHeight($file);

			# Try again
			if(!str::exec($cmd, $output)){
				// If there is still an issue

				# Log the command and output
				$file['cmd'][] = $cmd;
				$file['output'][] = $output;

				# Pencils down
				return;
			}
		}

		# Log the command and output
		$file['cmd'][] = $cmd;
		$file['output'][] = $output;

		# Remove the PDF copy
		exec("rm {$file['tmp_name']}");

		# Add the pdftoppm suffix (this is added automatically from the above command)
		$file['tmp_name'] .= "-{$page_number}.jpg";

		# Resize to max width or height
		$cmd = "convert {$file['tmp_name']} -resize " . self::MAX_WIDTH_HEIGHT_PX . "x" . self::MAX_WIDTH_HEIGHT_PX . "\> {$file['tmp_name']} 2>&1";
		// This is only a problem if you're dealing with unique PDFs with very high DPIs

		# Log and execute the command
		$response = str::exec($cmd, $output);
		$file['cmd'][] = $cmd;
		$file['output'][] = $output;

		if(!$response){
			// If there is an issue
			return;
		}

		# Update the metadata
		$file['md5'] = md5_file($file['tmp_name']);
		$file['size'] = filesize($file['tmp_name']);
		$file['type'] = mime_content_type($file['tmp_name']);
		$file['ext'] = pathinfo($file['tmp_name'], PATHINFO_EXTENSION);
	}

	/**
	 * Assuming the file is an SVG, will look for any <style>
	 * tags, and convert all styles to inline styles. This is
	 * useful if the SVG is to be embedded into a DOCX and
	 * converted to a PDF, as the converter will disregard any
	 * styles in tags and only accept inline styles.
	 *
	 * @param array $file
	 */
	public static function inlineSvgCss(array $file): void
	{
		# Read the file, convert all <style> tags to inline CSS
		$doc = CssInliner::fromHtml(file_get_contents($file['tmp_name']))->inlineCss()->getDomDocument();

		# Get the SVG component from the DOM (which is now been converted to an HTML file)
		$svg = $doc->saveXML($doc->getElementsByTagName("svg")->item(0));

		# Update the local file with the updated SVG content
		file_put_contents($file['tmp_name'], $svg);
	}

	/**
	 * Enlarge the SVG so that when it is converted to PNG, it's not blurry
	 * or too small. Only updates the width/height, base don the viewBox,
	 * but does not touch the viewBox.
	 *
	 * @param array     $file
	 * @param float|int $multiplier The default multiplier is 3. It's rather arbitrary but seems to work.
	 */
	public static function enlargeSvg(array &$file, float $multiplier = 3): void
	{
		# Load the SVG (as an XML)
		$doc = new \DOMDocument();
		$doc->loadXML(file_get_contents($file['tmp_name']));

		# Get the SVG
		$tags = $doc->getElementsByTagName("svg");

		# Go through it (there is only one)
		foreach($tags as $tag){

			# Go through each attribute
			foreach($tag->attributes as $attribute){
				if(strtolower($attribute->name) == "viewbox"){
					// If the attribute is the viewBox

					# Grab the values from the viewBox and break it up
					[$x, $y, $width, $height] = explode(" ", $attribute->value);

					# The width/height is based on the viewBox multiplied with the multiplier
					$width = ($width - $x) * $multiplier;
					$height = ($height - $y) * $multiplier;

					# Add the width for the DOCX doc fills
					$file['width'] = $width / (72 / 2.54);
					/**
					 * Here we're dividing the width
					 * (in pixels) with the DPI, to get
					 * an approximate CM width number.
					 *
					 * 72 DPI seems to be the default.
					 * 1 px/cm = 2.54 px/inch
					 */

					# Add width/height attributes, and set the multiplied number
					$tag->setAttribute("width", $width);
					$tag->setAttribute("height", $height);

					# Save the updated SVG
					file_put_contents($file['tmp_name'], $doc->saveXML());

					return;
				}
			}
		}
	}

	/**
	 * @param array     $file
	 * @param int|null  $max_width
	 * @param int|null  $max_height
	 * @param int|null  $quality
	 * @param bool|null $base64_encode
	 * @param bool|null $return_array
	 *
	 * @return array|string|null
	 * @throws \ImagickException
	 */
	public static function getResizedImage(array &$file, ?int $max_width = 0, ?int $max_height = 0, ?int $quality = 50, ?bool $base64_encode = NULL, ?bool $return_array = NULL)
	{
		# Open ImageMagik
		$im = new \Imagick();

		# Just in case the file needs converting
		Convert::webp($file);
		Convert::emf($file);
		// This should only apply for legacy files

		# If the image file is actually a PDF
		if($file['pdf_info']){
			# Set the resolution to 50DPI (it's low, but that's OK, we're making an even smaller thumbnail)
			$im->setResolution(50, 50);

			# Read the first page (only)
			$im->readImage("{$file['tmp_name']}[0]");

			# Set the background to white
			$im->setImageBackgroundColor('#FFFFFF');

			# Flatten image (this will prevent pages with transparencies to go black)
			$im = $im->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
		}

		# All other actual image files
		else {
			# Load the image data
			try {
				$im->readImage($file['tmp_name']);

				# Resize the image (if required)
				$file['scale_ratio'] = self::resizeImage($im, $max_width, $max_height);
				//This will return 1 if the image is not resized
			}

				# If there is an error
			catch(\ImagickException $e) {

				# Log the error, but the show must go on
				Log::getInstance()->error([
					"display" => str::isDev(),
					//No need to show this to the end user at this point
					"title" => "Unable to shrink image: " . json_encode($file),
					"message" => $e->getMessage(),
					"trace" => str::backtrace(true, false),
				]);

				return NULL;
			}
		}

		# Set image format
		$im->setImageFormat("jpg");

		# Set image quality
		if($quality){
			//if the quality variable is set
			$im->setImageCompressionQuality($quality);
		}

		# Grab the thumbnail data
		$data = $im->getImageBlob();

		if($base64_encode){
			$type = "image/jpg";
			$data = 'data:' . $type . ';base64,' . base64_encode($data);
		}

		if($return_array){
			$data = [
				"data" => $data,
				"width" => $new_width,
				"height" => $new_height,
			];
		}

		return $data;
	}

	/**
	 * Resizes the image. Returns the scale ratio.
	 * If the image is already smaller than the max width/height,
	 * it will return 1, meaning the image was not resized.
	 *
	 * @param \Imagick $im
	 * @param int|null $max_width
	 * @param int|null $max_height
	 *
	 * @return float
	 * @throws \ImagickException
	 */
	private static function resizeImage(\Imagick &$im, ?int $max_width = 0, ?int $max_height = 0): float
	{
		if(!$max_width && !$max_height){
			//if no max width or height is set
			return 1;
		}

		# Get the current image width and height
		$width = $im->getImageWidth();
		$height = $im->getImageHeight();

		if($width < $max_width && $height < $max_height){
			//if the image is already smaller than the max width/height
			return 1;
		}

		# Get the aspect ratio
		$aspect_ratio = $width / $height;

		# If the width is larger than the height
		if($width > $height){
			$new_width = $max_width;
			$new_height = $max_width / $aspect_ratio;
		}

		# If the height is larger than the width
		else {
			$new_height = $max_height;
			$new_width = $max_height * $aspect_ratio;
		}

		# Set the max width / height
		$im->resizeImage($new_width, $new_height, \Imagick::FILTER_LANCZOS, 1);

		# Return the scale ratio
		return $new_width / $width;
	}

	/**
	 * Relies on the ImageTracer Java file.
	 *
	 * @param array $file
	 *
	 * @throws \Exception
	 * @link https://github.com/jankovicsandras/imagetracerjava
	 */
	public static function convertToVector(array &$file, bool $bw = true): void
	{
		if($bw){
			$cmd = "/home/sina/.cargo/bin/vtracer --colormode bw --filter_speckle 10 --mode polygon --segment_length 10 --input {$file['tmp_name']} --output {$file['tmp_name']}.svg";
		}

		else {
			$cmd = "/home/sina/.cargo/bin/vtracer --filter_speckle 10 --mode polygon --segment_length 10 --input {$file['tmp_name']} --output {$file['tmp_name']}.svg";
		}

		exec($cmd, $output, $return_var);
		if($return_var){
			throw new \Exception("{$return_var}: Unable to convert file {$file['tmp_name']} to vector: " . implode("<br>", $output) . "<br> Please try again.");
		}

		# Get rid of the raster version
		//		exec("rm {$file['tmp_name']}");

		# Update the tmp name
		$file['tmp_name'] .= ".svg";

		# Ensure the name nas a .svg extension
		$file['name'] = $file['name'] . ".svg";

		# Set the size
		$file['size'] = filesize($file['tmp_name']);

		# Record the MD5 hash of the file
		$file['md5'] = md5_file($file['tmp_name']);

		# Get the file extension
		$file['ext'] = $ext;
		//the extension is stored in all lowercase only

		# Get the mime type
		$file['mime_type'] = mime_content_type($file['tmp_name']);
	}

	public static function getHSLMeanLightness(\Imagick $imagick): float
	{
		// convert to HSL - Hue, Saturation and LIGHTNESS
		$imagick->transformImageColorspace(\Imagick::COLORSPACE_HSL);
		// Get statistics for the LIGHTNESS
		$Lchannel = $imagick->getImageChannelMean(\Imagick::CHANNEL_ALL);
		// Calcualte the mean
		return $Lchannel['mean'] / $imagick->getQuantum();
	}

	/**
	 * Returns a float where 0 is black and 1 is white.
	 *
	 * @param array $file
	 *
	 * @return float
	 */
	public static function getMeanLightness(array $file, \Imagick $imagick): float
	{
		$cmd = "identify -format '%[mean]' {$file['tmp_name']}";
		$lightness = shell_exec($cmd);
		return $lightness / $imagick->getQuantum();
	}

	/**
	 * Returns true if the image has an alpha channel (transparency).
	 *
	 * @param array $file
	 *
	 * @return bool
	 * @link https://stackoverflow.com/questions/2581469/detect-alpha-channel-with-imagemagick
	 */
	public static function hasTransparency(array $file): bool
	{
		$cmd = "identify -format '%A' {$file['tmp_name']}";
		return shell_exec($cmd) == "Blend";
	}

	/**
	 * @param array $file
	 *
	 * @throws \ImagickException
	 * @link https://stackoverflow.com/a/24511102/429071
	 */
	public static function convertToMonochrome(array &$file): void
	{
		# Open ImageMagik
		$im = new \Imagick();

		# Load the image data
		try {
			$im->readImage($file['tmp_name']);
		}

			# If there is an error
		catch(\ImagickException $e) {

			# Log the error, but the show must go on
			Log::getInstance()->error([
				"display" => str::isDev(),
				//No need to show this to the end user at this point
				"title" => "Unable to read image to then crop: " . json_encode($file),
				"message" => $e->getMessage(),
				"trace" => str::backtrace(true, false),
			]);

			return;
		}

		$lightness = Doc::getMeanLightness($file, $im);

		# Make grayscale
		$im->setImageType(\Imagick::IMGTYPE_GRAYSCALE);

		$size_px = 3000;

		# Enlarge the image
		[$new_width, $new_height] = self::getProportionateWidthAndHeight($im, $size_px, $size_px, true);
		$im->resizeImage($new_width, $new_height, \Imagick::FILTER_SINC, 4);

		if($lightness < .75){
			//If the image is only up to 75% white, increase contrast and make it lighter
			$im->levelImage(50 * $im->getQuantum() / 255, 3, 150 * $im->getQuantum() / 255);
		}

		# Set the monochrome threshold
		$int = 200;
		$thresholdColor = "RGB({$int}, {$int}, {$int})";
		$im->blackThresholdImage($thresholdColor);
		$im->whiteThresholdImage($thresholdColor);

		# Force just black and white colours
		//		self::forceBlackAndWhite($im);
		// We don't need to do it here, it will be done by the vector converter

		# Shrink to half the size after growing and converting to monochrome
		[$new_width, $new_height] = self::getProportionateWidthAndHeight($im, round($size_px / 3), round($size_px / 3));
		$im->resizeImage($new_width, $new_height, \Imagick::FILTER_SINC, .5);

		self::setFile($file, $im, "png");

		$im->destroy();

		# Make transparent
		$cmd = "convert {$file['tmp_name']} -fuzz 2% -transparent white {$file['tmp_name']}.transparent && mv {$file['tmp_name']}.transparent {$file['tmp_name']}";
		exec($cmd, $output, $return_var);
		if($output){
			throw new \Exception("Unable to add transparency to file {$file['tmp_name']}: " . implode("<br>", $output) . "<br> Please try again.");
		}
	}

	private static function setFile(array &$file, \IMagick $im, string $ext): void
	{
		if($ext != "pdf"){
			unset($file['pdf_info']);
		}

		# Add the file extension to the tmp name value
		$file['tmp_name'] = substr($file['tmp_name'], (strlen($ext) + 1) * -1) == ".{$ext}" ? $file['tmp_name'] : $file['tmp_name'] . ".{$ext}";

		# Make a copy
		file_put_contents($file['tmp_name'], $im->getImageBlob());

		# Ensure the name also nas a .png extension
		$file['name'] = substr($file['name'], (strlen($ext) + 1) * -1) == ".{$ext}" ? $file['name'] : $file['name'] . ".{$ext}";

		# Set the size
		$file['size'] = filesize($file['tmp_name']);

		# Record the MD5 hash of the file
		$file['md5'] = md5_file($file['tmp_name']);

		# Get the file extension
		$file['ext'] = $ext;
		//the extension is stored in all lowercase only

		# Get the mime type
		$file['mime_type'] = mime_content_type($file['tmp_name']);
	}

	public static function cropFile(array &$file, ?float $x, ?float $y, ?float $width, ?float $height, ?float $angle): void
	{
		# Open ImageMagik
		$im = new \Imagick();

		# If the image file is actually a PDF
		if($file['pdf_info']){
			# Set the resolution to 50DPI (it's low, but that's OK, we're making an even smaller thumbnail)
			$im->setResolution(50, 50);

			# Read the first page (only)
			$im->readImage("{$file['tmp_name']}[0]");

			# Set the background to white
			$im->setImageBackgroundColor('#FFFFFF');

			# Flatten image (this will prevent pages with transparencies to go black)
			$im = $im->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
		}

		# All other actual image files
		else {
			# Load the image data
			try {
				$im->readImage($file['tmp_name']);
			}

				# If there is an error
			catch(\ImagickException $e) {

				# Log the error, but the show must go on
				Log::getInstance()->error([
					"display" => str::isDev(),
					//No need to show this to the end user at this point
					"title" => "Unable to read image to then crop: " . json_encode($file),
					"message" => $e->getMessage(),
					"trace" => str::backtrace(true, false),
				]);

				return;
			}
		}

		# Set image format to PNG
		$im->setImageFormat("png");

		# Rotate the image
		if($angle){
			$im->rotateimage("white", $angle);
		}

		# Get the scale ratio (if one has been passed)
		$ratio = $file['scale_ratio'] ?: 1;

		$x = $x / $ratio;
		$y = $y / $ratio;
		$width = $width / $ratio;
		$height = $height / $ratio;

		# Crop the image
		$im->cropImage($width, $height, $x, $y);

		self::setFile($file, $im, "png");

		$im->destroy();
	}

	private static function getProportionateWidthAndHeight(\Imagick $im, ?int $max_width = NULL, ?int $max_height = NULL, ?bool $grow = NULL): array
	{
		$width = $im->getImageWidth();
		$height = $im->getImageHeight();

		# Ensure the resizing maintains the aspect ratio
		if($width > $height){
			if(!$grow && $width < $max_width){
				$new_width = $width;
			}
			else {
				$new_width = $max_width;
			}

			$divisor = $width / $new_width;
			$new_height = floor($height / $divisor);
		}

		else {
			if(!$grow && $height < $max_height){
				$new_height = $height;
			}
			else {
				$new_height = $max_height;
			}

			$divisor = $newheight ? $height / $newheight : $height;
			$new_width = floor($width / $divisor);
		}

		return [$new_width, $new_height];
	}

	public static function forceBlackAndWhite(\Imagick &$imagick, $ditherMethod = \Imagick::DITHERMETHOD_NO)
	{
		$palette = new \Imagick();
		$palette->newPseudoImage(1, 2, 'gradient:black-white');
		$palette->setImageFormat('png');
		//$palette->writeImage('palette.png');

		// Make the image use these palette colors
		$imagick->remapImage($palette, $ditherMethod);
		$imagick->setImageDepth(1);
	}

	/**
	 * If a file is a PDF, checks to see if either width or height are bigger than the
	 * max number of allowed inches. If so, will rescale down the page to the max inch size.
	 *
	 * @param array          $file
	 * @param float|int|null $max_in     The default is set to 17 inches, the max width/height that Microsoft Cognitive
	 *                                   Services accepts.
	 *
	 * @return string|null
	 * @throws \Exception
	 * @link https://urmaul.com/blog/imagick-filters-comparison/
	 */
	public static function shrinkPdf(array &$file, ?float $max_in = 17): ?string
	{
		# Ensure file is PDF
		if(!$file['pdf_info']){
			//if the file isn't a PDF
			return NULL;
		}

		# Ensure file is too big
		if(($file['pdf_info']['page_height_in'] <= $max_in && $file['pdf_info']['page_width_in'] <= $max_in)){
			//If neither the width nor height are bigger than the max width/height in inches
			return NULL;
		}

		$max_points = $max_in * 72;

		$cmd = "gs " .
			"-o {$file['tmp_name']}-{$max_points} " .
			"-sDEVICE=pdfwrite " .
			"-dPDFSETTINGS=/prepress " .
			"-dCompatibilityLevel=1.4 " .
			"-dFIXEDMEDIA " .
			"-dPDFFitPage " .
			"-dDEVICEWIDTHPOINTS={$max_points} " .
			"-dDEVICEHEIGHTPOINTS={$max_points} " .
			"-f {$file['tmp_name']}";

		if(!str::exec($cmd, $output)){
			throw new \Exception("Unable to resize PDF file {$file['tmp_name']}: " . implode("<br>", $output) . "<br> After running <code>{$cmd}</code>. Please try again.");
		}

		# Remove the input tmp file
		exec("rm {$file['tmp_name']}");

		# Rename the output tmp file to the input tmp file
		rename("{$file['tmp_name']}-{$max_points}", $file['tmp_name']);

		# Return the tmp file name
		return $file['tmp_name'];
	}

	/**
	 * If an image or PDF is monochrome, or
	 * uses less than 300 unique colours, this
	 * method will make a copy of the document
	 * as a PNG and increase the contrast
	 * and reduce the grays to increase legibility.
	 *
	 * The copied file name and any metadata
	 * will be saved in the png key.
	 *
	 * @param array $file
	 *
	 * @return string|null The method will return NULL if no copy is made, or the png filename if so
	 * @throws \ImagickException
	 */
	public static function formatSinglePageMonochromeImages(array &$file): ?string
	{
		# Open ImageMagik
		$imagick = new \Imagick();

		# Load PDF slightly differently
		if($file['pdf_info']){
			//if the file is a PDF

			# Ensure file only has one page
			if($file['pdf_info']['pages'] != 1){
				// If the PDF has more than one pages, we're not doing anything with the doc at the moment

				# Pencils down
				return NULL;
			}

			$filename = "{$file['tmp_name']}[0]";
			//ImageMagick pages run from zero

			# Set the resolution
			$imagick->setResolution(200, 200);
			/**
			 * We're setting the resolution high-ish, otherwise the
			 * image won't be very clear. With this resolution, we
			 * don't need to multiply the image size to ensure that
			 * it is legible.
			 */
		}

		else {
			$filename = $file['tmp_name'];
		}

		# Read the first (and only) page
		$imagick->readImage($filename);

		# We're only looking for monochrome (or close to monochrome) images
		if($imagick->getImageColors() > 300){
			//Image has more than 300 unique colours, we're not interested

			# Clear any cache
			$imagick->clear();

			# Pencils down
			return NULL;
		}

		# If the doc has only black/white (true monochrome), we need to do some extra work
		if($imagick->getImageColors() < 6){
			// If the image is true monochrome (and not just b/w)

			# Get the image dimensions
			$width = $imagick->getImageWidth();
			$height = $imagick->getImageHeight();

			# Slightly enlarge the image
			$imagick->resizeImage($width * 1.25, $height * 1.25, \Imagick::FILTER_POINT, 1);

			# Slightly blur the photo
			$imagick->blurImage(2, 1);
			// We're doing this to re-introduce more colours in the photo
		}

		# The output of this process is a lossless image
		$imagick->setImageFormat("png");

		# Set the background to white
		$imagick->setImageBackgroundColor('#FFFFFF');
		// Doesn't seem to make a difference, but is added just in case, doesn't cost anything in terms of time

		# Flatten image (this will prevent pages with transparencies to go black)
		$imagick = $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
		// Needs to be in place for the trimming to work

		# Aggressively trim away white space
		$imagick->trimImage(40000);
		/**
		 * The fuzz value is based on the quantum range,
		 * which is usually 65,535 in these kinds of
		 * images.
		 *
		 * @link https://www.php.net/manual/en/imagick.getquantumrange.php
		 * @link https://stackoverflow.com/questions/27356055/trimming-extra-white-background-from-image-using-imagemagick-in-php
		 */

		# Set the image levels
		if(!self::setCurve($imagick, 100, 200)){
			// If we can't set a curve, we'll set levels instead
			self::setLevels($imagick, .2, 6, 1);
		}

		$file['png']['tmp_name'] .= "{$file['tmp_name']}.png";

		# Store the page as a PNG
		$imagick->writeImage("png:" . $file['png']['tmp_name']);

		# And we're done with ImageMagick
		$imagick->clear();

		# Set the metadata
		$file['png']['type'] = "image/png";
		$file['png']['mime_type'] = "image/png";
		$file['png']['ext'] = "png";
		$file['png']['md5'] = md5_file($file['png']['tmp_name']);
		$file['png']['size'] = filesize($file['png']['tmp_name']);

		# Return the png filename
		return $file['png']['tmp_name'];
	}

	/**
	 * Set image level.
	 *
	 * @param \Imagick   $imagick
	 * @param float|null $black Percent black, float from 0 to 1
	 * @param int        $gamma Gamma level, int from 0 to 10
	 * @param float|null $white Percent white, float from 0 to 1
	 *
	 * @throws \ImagickException
	 */
	public static function setLevels(\Imagick &$imagick, ?float $black = 0.2, int $gamma = 6, ?float $white = 1): void
	{
		# Get the quantum number
		$quantum = $imagick->getQuantumRange();
		$quantum = $quantum['quantumRangeLong'];

		# Set the level
		$imagick->levelImage($black * $quantum, $gamma, $white * $quantum);
	}

	/**
	 * x1/y1 is the curve's single inflexion point, the values
	 * correspond to the Photoshop Curve input and output values
	 * respectively.
	 *
	 * Not all inflexion coordinates will translate to coefficients,
	 * if we're unable to generate coefficients, the method
	 * return false.
	 *
	 * @param \Imagick $imagick
	 * @param int      $x1
	 * @param int      $y1
	 * @param int      $max We're using the Photoshop scale of 255, but ImageMagick uses a 0-1 float.
	 *
	 * @return bool
	 * @throws \ImagickException
	 */
	public static function setCurve(\Imagick &$imagick, int $x1 = 100, int $y1 = 200, int $max = 255): bool
	{
		$i = 0;
		$start_y = 0;
		$end_x = 1;

		do {
			# Get the calculated coefficients based on our gradient curve
			$cmd = "cd /var/www/tmp/ && ./im_fx_curves -c 0,{$start_y} " . ($x1 / $max) . "," . ($y1 / $max) . " 1,{$end_x}";
			$coefficients = trim(shell_exec($cmd));

			$start_y += 0.01;
			$end_x -= 0.01;

			$i++;
			if($i == 10){
				return false;
			}
		} while(!$coefficients);

		# Apply the curve to the image
		$imagick->functionImage(\Imagick::FUNCTION_POLYNOMIAL, explode(",", $coefficients));

		return true;
	}

	public static function svgHasContent(?string $xml): bool
	{
		# Ensure the SVG tag isn't empty
		$dom = new \DOMDocument();
		$dom->loadXML($xml);
		$svg = $dom->getElementsByTagName('svg')->item(0);

		// Iterate over the child nodes and check if any of them are element nodes
		foreach($svg->childNodes as $child){
			if($child->nodeType === XML_ELEMENT_NODE){
				return true;
			}
		}

		return false;
	}

	/**
	 * Runs a series of pre-flight checks on an XLSX file
	 * to ensure it's a valid, unencrypted XLSX file.
	 * If any checks fail, an array with title/message
	 * is returned.
	 *
	 * If all checks pass, an empty array is returned.
	 *
	 * @param array $file
	 *
	 * @return array|string[]|null
	 */
	public static function runXlsxPreFlightChecks(array $file): array
	{
		# This only applies to XLSX files
		if(strtolower($file['ext']) != "xlsx"){
			return [];
		}

		# Ensure the file exists and is readable
		if(!is_file($file['tmp_name']) || !is_readable($file['tmp_name'])){
			return [
				"title" => "File did not upload correctly",
				"message" => "The file <code>{$file['name']}</code> does not exist or is not readable. Please try to upload the file again.",
			];
		}

		switch(self::detectContainerType($file['tmp_name'])) {
		case 'cfb':
			return [
				"title" => "File appears to be encrypted",
				"message" => "The file <code>{$file['name']}</code> appears to be encrypted or permission-protected.
				The system cannot read encrypted or permission-protected workbooks. Please remove any protection and try again.",
			];
		case NULL:
			return [
				"title" => "Unable to detect file type",
				"message" => "The file <code>{$file['name']}</code> does not appear to be a valid Excel file.
					Ensure you're able to open the file in Excel or another spreadsheet application before trying to upload it again.",
			];
		}

		$zip = new \ZipArchive();
		$res = $zip->open($file['tmp_name']);
		if($res !== true){
			return [
				"title" => "Unable to open file",
				"message" => "The file <code>{$file['name']}</code> doesn't seem to be a valid, unencrypted Excel file.
				Ensure you're able to open the file in Excel or another spreadsheet application uploading it again.",
			];
		}

		# Get a list of all files in the ZIP
		$files_in_zip = [];
		for($i = 0; $i < $zip->numFiles; $i++){
			$files_in_zip[] = $zip->getNameIndex($i);
		}

		# Basic sanity checks for a normal XLSX
		if(!in_array("[Content_Types].xml", $files_in_zip) || !in_array("xl/workbook.xml", $files_in_zip)){
			return [
				"title" => "File does not appear to be a valid Excel file",
				"message" => "The file <code>{$file['name']}</code> exists as a ZIP container but does not appear to be a valid Excel file.
				Ensure you're able to open the file in Excel or another spreadsheet application before trying to upload it again.",
			];
		}

		$zip->close();
		return [];
	}

	/**
	 * Detects the container type of a file based on its signature.
	 *
	 * @param string $path
	 *
	 * @return string|null
	 */
	private static function detectContainerType(string $path): ?string
	{
		$fh = fopen($path, 'rb');
		if(!$fh){
			throw new \RuntimeException("Cannot open file: $path");
		}
		$sig = fread($fh, 8);
		fclose($fh);

		// ZIP: PK\x03\x04 (or other PK signatures)
		if(strncmp($sig, "PK", 2) === 0){
			return 'zip';
		}

		// OLE/CFB: D0 CF 11 E0 A1 B1 1A E1
		if($sig === "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"){
			return 'cfb';
		}

		return NULL;
	}

}