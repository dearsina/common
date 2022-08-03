<?php

namespace App\Common\Doc;

use App\Common\Exception\BadRequest;
use App\Common\str;
use Pelago\Emogrifier\CssInliner;
use Smalot\PdfParser\RawData\RawDataParser;

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
		$output = (int)trim(shell_exec("qpdf --requires-password {$file['tmp_name']} ; echo \$?"));

		if($output){
			return false;
		}

		return true;
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
	public static function addPDFMetadata(array &$file): void
	{
		if(mime_content_type($file['tmp_name']) != "application/pdf"){
			//If the file is NOT a PDF, do nothing
			return;
		}

		# Run the pdfinfo command
		exec("pdfinfo {$file['tmp_name']}", $output, $return_var);

		# Ensure the PDF was readable (and error out if not)
		switch($return_var) {
		case 1: # Error opening a PDF file.
			throw new BadRequest("There was an error opening your PDF file <code>{$file['name']}</code>. Please ensure it is a valid PDF file and try again.");
		case 2: # Error opening an output file.
			throw new BadRequest("There was an error opening an output file for your PDF file <code>{$file['name']}</code>. Please try again.");
		case 3: # Error related to PDF permissions.
			throw new BadRequest("There was a permission error when opening your PDF file <code>{$file['name']}</code>. Please remove any passwords this PDF may have and try again.");
		case 99: # Other error.
			throw new BadRequest("There was an unknown error opening your PDF file <code>{$file['name']}</code>. Please ensure it is a valid PDF file and try again.");
		default: # No error reported
			break;
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

		# Get the file contents
		$contents = file_get_contents($file['tmp_name']);

		# Open the raw data parser
		$rawDataParser = new RawDataParser();

		# Create structure from raw data.
		[$xref, $data] = $rawDataParser->parseData($contents);

		if(isset($xref['trailer']['encrypt'])){
			// if the file is secured, assume it has text
			return true;
		}

		if(empty($data)){
			// if the file has no data, assume it's secured?
			return true;
		}

		$parser = new \Smalot\PdfParser\Parser();
		$pdf = $parser->parseFile($file['tmp_name']);
		$file['pdf_info']['text'] = $pdf->getText();

		return (bool)strlen($file['pdf_info']['text']);
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
				throw new \Exception("Unable to move uploaded file. Please try uploading again.");
			}

			$files[$i]['name'] = $file['name'][$i];
			$files[$i]['type'] = $file['type'][$i];
			$files[$i]['size'] = $file['size'][$i];

			# Record the MD5 hash of the file
			$files[$i]['md5'] = md5_file($tmp_name);

			# Get the file extension
			$files[$i]['ext'] = strtolower(pathinfo($file['name'][$i], PATHINFO_EXTENSION));
			//the extension is stored in all lowercase only

			# Get the mime type
			$files[$i]['mime_type'] = mime_content_type($tmp_name);

			# Update the tmp_name to the new tmp_name
			$files[$i]['tmp_name'] = $tmp_name;
		}

		if($multiple){
			return $files;
		}

		return reset($files);
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

		return NULL;
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

		# Delete any originals also
		if($originals = Convert::getOriginals($file)){
			foreach($originals as $method => $method_file){
				if(file_exists($method_file['tmp_name'])){
					//if the file exists of course
					unlink($method_file['tmp_name']);
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
	public static function fileIsFont(array $file, ?bool $throw_error = NULL): bool
	{
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
		unlink($svg_tmp_name);
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

	public static function convertHeic(array $file, ?int $quality = 50): ?string
	{
		# Ensure file is indeed a HEIC file
		if($file['ext'] != 'heic'){
			//if the file isn't a HEIC file
			return NULL;
		}

		# Open ImageMagik
		$imagick = new \Imagick();

		# Read the image
		$imagick->readImage($file['tmp_name']);

		# Set image quality
		if($quality){
			//if the quality variable is set
			$imagick->setImageCompressionQuality($quality);
		}

		# Set image format
		$imagick->setImageFormat("jpeg");

		# Create a page specific file array
		$file_page = [
			'name' => "{$file['name']}.jpg",
			'type' => 'image/jpeg',
			'tmp_name' => "{$file['tmp_name']}-jpg",
			'size' => $imagick->getImageLength(),
		];

		# Store the page as a JPG
		$imagick->writeImage("jpg:" . $file_page['tmp_name']);

		# Return the new tmp name
		return $file_page['tmp_name'];
	}

	/**
	 * If a file is a PDF, checks to see if either width or height are bigger than the
	 * max number of allowed inches. If so, will rescale down the image using the given
	 * resolution, save each page using the quality JPG and the put each JPG page
	 * back together into a single PDF, who's name will be passed back.
	 *
	 * This was how long it takes to resize a 5906x5906 JPEG image to 1181x1181.
	 * This was in 2010, the speeds I'm sure have improved, but useful reference
	 * either way.
	 *
	 * FILTER_POINT took: 0.334532976151 seconds
	 * FILTER_BOX took: 0.777871131897 seconds
	 * FILTER_TRIANGLE took: 1.3695909977 seconds
	 * FILTER_HERMITE took: 1.35866093636 seconds
	 * FILTER_HANNING took: 4.88722896576 seconds
	 * FILTER_HAMMING took: 4.88665103912 seconds
	 * FILTER_BLACKMAN took: 4.89026689529 seconds
	 * FILTER_GAUSSIAN took: 1.93553304672 seconds
	 * FILTER_QUADRATIC took: 1.93322920799 seconds
	 * FILTER_CUBIC took: 2.58396601677 seconds
	 * FILTER_CATROM took: 2.58508896828 seconds
	 * FILTER_MITCHELL took: 2.58368492126 seconds
	 * FILTER_LANCZOS took: 3.74232912064 seconds
	 * FILTER_BESSEL took: 4.03305602074 seconds
	 * FILTER_SINC took: 4.90098690987 seconds
	 *
	 * @param array          $file
	 * @param float|int|null $max_in     The default is set to 17 inches, the max width/height that Microsoft Cognitive
	 *                                   Services accepts.
	 * @param int|null       $resolution The default is set to 144, which will retain a sufficient level of quality in
	 *                                   the photo.
	 * @param int|null       $quality    The default is 50, which for the purposes of OCR is sufficient.
	 *
	 * @return string|null
	 * @throws \ImagickException
	 * @link https://urmaul.com/blog/imagick-filters-comparison/
	 */
	public static function shrinkPdf(array &$file, ?float $max_in = 17, ?int $resolution = 144, ?int $quality = 50): ?string
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

		# Create a JPG for each page
		for($i = 0; $i < $file['pdf_info']['pages']; $i++){
			# ImageMagik pages run from zero, but PDFs run from 1
			$page = $i + 1;

			# Open ImageMagik
			$imagick = new \Imagick();

			# Set the resolution
			$imagick->setResolution($resolution, $resolution);

			# Read a page
			$imagick->readImage("{$file['tmp_name']}[{$i}]");

			# Set the background to white
			$imagick->setImageBackgroundColor('#FFFFFF');

			# Flatten image (this will prevent pages with transparencies to go black)
			$imagick = $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);

			# Resize so that it's at most the max size accepted
			$imagick->resizeImage(
				min($imagick->getImageWidth(), $max_in * $resolution),
				min($imagick->getImageHeight(), $max_in * $resolution),
				\Imagick::FILTER_UNDEFINED,
				1,
				true
			);
			/**
			 * PDF sizes are in points (pts) which is calculated based
			 * on the number of pixels divided by the resolution. Thus
			 * the max width or height is the number of max inches (pts/72),
			 * multiplied by the set DPI. This way, when the PDF is
			 * produced, it will list the width/heights in inches, and
			 * since the file has been shrunk (otherwise we wouldn't be here),
			 * one of the sides will be at the max number of inches.
			 */

			# Set image quality
			if($quality){
				//if the quality variable is set
				$imagick->setImageCompressionQuality($quality);
			}

			# Create a page specific file array
			$file_page = [
				'name' => "{$file['name']}.{$page}.jpg",
				'type' => 'image/jpeg',
				'tmp_name' => "{$file['tmp_name']}-{$page}",
				'size' => $imagick->getImageLength(),
				'page' => $page,
			];

			# Store the page as a JPG
			$imagick->writeImage("jpg:" . $file_page['tmp_name']);

			# Save the details in an array
			$file_pages[] = $file_page;
		}

		# Get all the image page tmp file names as an array
		$images = array_column($file_pages, "tmp_name");

		# Create the PDF tmp filename
		$file['tmp_name_png'] = "{$file['tmp_name']}.pdf";

		# Load the page images
		$pdf = new \Imagick($images);

		# Set the format of the output to be PDF
		$pdf->setImageFormat('pdf');

		# Write all the images as individual pages in the PDF
		$pdf->writeImages($file['tmp_name_png'], true);

		# Remove all the temporary page JPGs
		array_map("unlink", $images);

		# Return the new PDF temporary file name
		return $file['tmp_name_png'];
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
}