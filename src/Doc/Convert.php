<?php

namespace App\Common\Doc;

use App\Common\Exception\BadRequest;
use App\Common\Log;
use App\Common\str;
use App\Doc\Doc;

/**
 * The convert class contains methods that will
 * convert a file to be better suited for OCR
 * text extraction.
 *
 * It will retain the original, and make a copy
 * with one or more of the following suffixes:
 *
 * **HEIC**
 * The original heic image, while a JPG copy
 * is placed as the main document.
 *
 * **BW**
 * The original b/w or monochrome image, while
 * a PNG copy is placed as the main document.
 *
 * **BIG**
 * The original too big PDF, while a shrunk
 * PDF is placed as the main document.
 */
class Convert {
	const GLUE = "_";

	/**
	 * Runs through all the conversions.
	 *
	 * @param array $file
	 */
	public function all(array &$file): void
	{
		Convert::rotate($file);
		Convert::heic($file);
		Convert::xfa($file);
		Convert::webp($file);
		Convert::emf($file);
		Convert::bw($file);
		Convert::big($file);
		Convert::mirror($file);
	}

	public static function getOriginals(array $file): ?array
	{
		if($file['originals']){
			$file['original'] = $file['originals'];
		}

		if(!$file['original']){
			return NULL;
		}

		foreach($file['original'] as $method => $method_file){
			if(!is_array($method_file)){
				//if it's only an explanation as to why this method was NOT used, jog on
				continue;
			}
			if($method_file['md5'] == $file['md5']){
				//if the original file is the same as the current file, jog on
				continue;
			}
			
			if($method_file){
				$originals[$method] = $method_file;
			}
		}

		return $originals;
	}

	/**
	 * Given a client doc array, will return a blob_id, filename,
	 * and type of the *original* client doc, if the client doc was
	 * altered.
	 *
	 * <code>
	 * [$blob_id, $name, $type] = Convert::getOriginal($client_doc);
	 * </code>
	 *
	 * @param array $client_doc
	 *
	 * @return array
	 */
	public static function getOriginal(array $client_doc): array
	{
		if($originals = Convert::getOriginals($client_doc)){
			foreach($originals as $method => $original_client_doc){
				$blob_id = ($client_doc['bad_doc_id'] ?: $client_doc['client_doc_id']) . Convert::GLUE . $method;
				// Bad docs also use this method
				$name = $original_client_doc['name'];
				$type = $original_client_doc['type'];
			}
		}

		else {
			$blob_id = $client_doc['bad_doc_id'] ?: $client_doc['client_doc_id'];
			// Bad docs also use this method
			$name = $client_doc['name'];
			$type = $client_doc['type'];
		}

		return [$blob_id, $name, $type];
	}

	/**
	 * Will mirror the image if it's a PDF that has no text.
	 * This is primarily used to fix selfies where the flip
	 * feature was enabled on the camera.
	 *
	 * @param array $file
	 *
	 * @return void
	 * @throws \ImagickException
	 */
	public static function mirror(array &$file): void
	{
		# We only need to do this once
		if(Convert::hasAlreadyBeenProcessed($file, __FUNCTION__)){
			return;
		}

		# Fire up ImageMagick
		$imagick = new \Imagick();

		# Read the image
		$imagick->readImage($file['tmp_name']);

		# Load PDF slightly differently
		if($file['pdf_info']){
			//if the file is a PDF

			# We're not interested in PDFs that have text
			if($file['pdf_info']['text']){
				// They don't need flipping
				$file['original'][__FUNCTION__] = "Has text.";
				return;
			}

			# Keep a copy of the original, rename the file to avoid it being overwritten
			$original = Convert::makeCopy($file, __FUNCTION__);

			# For each page, flip it
			foreach ($imagick as $page) {
				$clone = clone $page;
				$clone->flipImage();
				$imagick->addImage($clone);
				$clone->clear();
			}

			$imagick->resetIterator();
			$imagick->setImageFormat("pdf");

			# Save the image
			$imagick->writeImages($file['tmp_name'], true);
		}

		else {
			# Keep a copy of the original, rename the file to avoid it being overwritten
			$original = Convert::makeCopy($file, __FUNCTION__);

			# Mirror the image
			$imagick->flopImage();

			# Store it in the same format as it came in
			$imagick->writeImage("{$file['ext']}:" . $file['tmp_name']);
		}

		# And we're done with ImageMagick
		$imagick->clear();

		# Set the new metadata
		clearstatcache();
		/**
		 * Data fetched by filesize() is "statcached",
		 * we need to clear it as the same file name
		 * has a different size now.
		 */

		$file['md5'] = md5_file($file['tmp_name']);
		$file['size'] = filesize($file['tmp_name']);

		# Attach the original back
		$file['original'][__FUNCTION__] = $original;
	}

	public static function xfa(array &$file): void
	{
		# We only need to do this once
		if(Convert::hasAlreadyBeenProcessed($file, __FUNCTION__)){
			return;
		}

		# Ensure the file was produced by Adobe LiveCycle Designer
		if($file['pdf_info']['producer'] != 'Adobe LiveCycle Designer ES 9.0'){
			return;
		}

		Log::getInstance()->warning([
			"title" => "XFA Form",
			"message" => "The document {$file['name']} is an XFA form.
			It will now be converted to a standard PDF.
			The conversion process may take up to 2 minutes.",
		], true);

		# Keep a copy of the original, rename the file to avoid it being overwritten
		$original = Convert::makeCopy($file, __FUNCTION__);

		# Add a suffix to the tmp name before converting
		rename($file['tmp_name'], $file['tmp_name'] . ".pdf");
		// Otherwise, Adobe Reader XI will struggle to open the file

		# Load the converter
		$xfa = new XfaPdf();

		# Convert the file
		$converted_tmp_name = $xfa->convert($file['tmp_name'].".pdf");

		# Remove the original file
		unlink($file['tmp_name']);

		# Save the new file
		$file['tmp_name'] = $converted_tmp_name;

		# Some details have changed
		$file['md5'] = md5_file($file['tmp_name']);
		$file['size'] = filesize($file['tmp_name']);
		Doc::addPDFMetadata($file);

		# Attach the original back
		$file['original'][__FUNCTION__] = $original;
	}

	/**
	 * Converts a HEIC image file to JPG because
	 * HEIC is not accepted by Azure and most
	 * browsers yet.
	 *
	 * @param array    $file
	 * @param int|null $quality
	 *
	 * @throws \ImagickException
	 */
	public static function heic(array &$file, ?int $quality = 50): void
	{
		# We only need to do this once
		if(Convert::hasAlreadyBeenProcessed($file, __FUNCTION__)){
			return;
		}

		# Ensure file is indeed a HEIC file
		if($file['ext'] != 'heic'){
			//if the file isn't a HEIC file
			return;
		}

		# Keep a copy of the original, rename the file to avoid it being overwritten
		$original = Convert::makeCopy($file, __FUNCTION__);

//		# Try using ImageMagick first
//		try {
//			# Open ImageMagick
//			$imagick = new \Imagick();
//
//			# Read the image
//			$imagick->readImage($original['tmp_name']);
//
//			# Set image quality
//			if($quality){
//				//if the quality variable is set
//				$imagick->setImageCompressionQuality($quality);
//			}
//
//			# Set image format
//			$imagick->setImageFormat("jpeg");
//
//			# Store the HEIC as a JPG
//			$imagick->writeImage("jpg:" . $file['tmp_name']);
//
//			# And we're done with ImageMagick
//			$imagick->clear();
//
//			# Set the new metadata
//			clearstatcache();
//		}
//
//		# Failing that, use the old-fashioned way (command line)
//		catch (\ImagickException $e) {

		/**
		 * ImageMagick struggles with this and crashes the thread.
		 * Command line works fine. ImageMagick code kept just in case.
		 */

			# Command
			$command = "magick {$file['tmp_name']} -quality {$quality} {$file['tmp_name']}.jpg";
			# Execute the command

			$output = shell_exec($command);
			// If the command has an output (that's bad news)

			# Return the last bit of the output, which is generally the error message
			if($output){
				$error = explode(":", $output);
				throw new \Exception(trim(end($error)));
			}

			# Remove the original HEIC
			unlink($file['tmp_name']);

			# Add the jpg suffix to the tmp name (to reflect the conversion to JPG)
			$file['tmp_name'] .= ".jpg";
//		}

		# Set the new metadata
		clearstatcache();
		/**
		 * Data fetched by filesize() is "statcached",
		 * we need to clear it as the same file name
		 * has a different size now.
		 */
		$file['type'] = "image/jpeg";
		$file['mime_type'] = "image/jpeg";
		$file['ext'] = "jpg";
		$file['name'] .= ".jpg";
		$file['md5'] = md5_file($file['tmp_name']);
		$file['size'] = filesize($file['tmp_name']);

		# Attach the original back
		$file['original'][__FUNCTION__] = $original;
	}

	/**
	 * Convert an image file to PDF.
	 * The original is not kept because this method
	 * is only meant to be called ad hoc on download,
	 * on an image file that has temporarily been
	 * copied down from a remote storage.
	 *
	 * @param array $file
	 *
	 * @return void
	 */
	public static function pdf(array &$file): void
	{
		# We only need to do this once
		if(Convert::hasAlreadyBeenProcessed($file, __FUNCTION__)){
			return;
		}

		# Ensure file is indeed an image file based on the type starting with "image"
		if(strpos($file['type'], "image") !== 0){
			//if the file isn't an image file
			return;
		}

		# Command
		$command = "magick {$file['tmp_name']} {$file['tmp_name']}.pdf";

		$output = shell_exec($command);
		// If the command has an output (that's bad news)

		# Return the last bit of the output, which is generally the error message
		if($output){
			$error = explode(":", $output);
			throw new \Exception(trim(end($error)));
		}

		# Remove the original image
		unlink($file['tmp_name']);

		# Add the pdf suffix to the tmp name (to reflect the conversion to PDF)
		$file['tmp_name'] .= ".pdf";

		# Set the new metadata
		clearstatcache();
		/**
		 * Data fetched by filesize() is "statcached",
		 * we need to clear it as the same file name
		 * has a different size now.
		 */

		$file['type'] = "application/pdf";
		$file['mime_type'] = "application/pdf";
		$file['ext'] = "pdf";
		$file['name'] .= ".pdf";
		$file['md5'] = md5_file($file['tmp_name']);
		$file['size'] = filesize($file['tmp_name']);
	}

	public static function rotate(array &$file): void
	{
		# We only need to do this once
		if(Convert::hasAlreadyBeenProcessed($file, __FUNCTION__)){
			return;
		}

		# Ensure file is JPG
		if($file['type'] != "image/jpeg"){
			// If the file isn't a JPG file
			return;
		}

		# See if the exif data is available
		if(!$exif = @\exif_read_data($file['tmp_name'])){
			return;
		}

		# Keep a copy of the original, rename the file to avoid it being overwritten
		$original = Convert::makeCopy($file, __FUNCTION__);

		# Store the exif data
		$file['exif'] = $exif;

		# If the exif says the image is rotated, rotate it
		if($exif['Orientation'] && in_array($exif['Orientation'], [3, 6, 8])){
			$cmd = "convert {$file['tmp_name']} -auto-orient {$file['tmp_name']}";
			exec($cmd);
		}

		# Set the new metadata
		clearstatcache();
		/**
		 * Data fetched by filesize() is "statcached",
		 * we need to clear it as the same file name
		 * has a different size now.
		 */
		$file['md5'] = md5_file($file['tmp_name']);
		$file['size'] = filesize($file['tmp_name']);

		# Attach the original back
		$file['original'][__FUNCTION__] = $original;
	}

	/**
	 * Converts a webp image file to PNG because
	 * webp is not accepted by Azure.
	 *
	 * @param array    $file
	 * @param int|null $quality
	 *
	 * @throws \ImagickException
	 */
	public static function webp(array &$file, ?int $quality = 50): void
	{
		# We only need to do this once
		if(Convert::hasAlreadyBeenProcessed($file, __FUNCTION__)){
			return;
		}

		# Ensure file is indeed a webp file
		if($file['ext'] != 'webp'){
			//if the file isn't a webp file
			return;
		}

		# Keep a copy of the original, rename the file to avoid it being overwritten
		$original = Convert::makeCopy($file, __FUNCTION__);

		# Command
		$command = "dwebp {$file['tmp_name']} -quiet -o {$quality} {$file['tmp_name']}.png";
		// -quiet means that it won't output anything unless there is an error

		$output = shell_exec($command);
		// If the command has an output (that's bad news)

		# Return the last bit of the output, which is generally the error message
		if($output){
			throw new \Exception(trim($output));
		}

		# Remove the original webp file
		unlink($file['tmp_name']);

		# Add the jpg suffix to the tmp name (to reflect the conversion to PNG)
		$file['tmp_name'] .= ".png";

		/**
		 * Data fetched by filesize() is "statcached",
		 * we need to clear it as the same file name
		 * has a different size now.
		 */
		$file['type'] = "image/png";
		$file['mime_type'] = "image/png";
		$file['ext'] = "png";
		$file['name'] .= ".png";
		$file['md5'] = md5_file($file['tmp_name']);
		$file['size'] = filesize($file['tmp_name']);

		# Attach the original back
		$file['original'][__FUNCTION__] = $original;
	}

	public static function emf(array &$file, ?int $quality = 50, ?string $ext = "png"): void
	{
		# We only need to do this once
		if(Convert::hasAlreadyBeenProcessed($file, __FUNCTION__)){
			return;
		}

		# Ensure file is indeed an emf file
		if($file['ext'] != 'emf'){
			//if the file isn't an emf file
			return;
		}

		# Keep a copy of the original, rename the file to avoid it being overwritten
		$original = Convert::makeCopy($file, ".emf");
		// We by design give the copy an .emf suffix, or else inkscape struggles and thinks it's an svg file

		# Command
		$command = "inkscape {$file['tmp_name']} --export-{$ext}={$file['tmp_name']}.{$ext} --export-width=$(echo \"\$(inkscape --query-width {$file['tmp_name']}) * 6\" | bc) --export-height=$(echo \"\$(inkscape --query-height {$file['tmp_name']}) * 6\" | bc) --export-area-drawing > /dev/null";
		// We're suppressing stdout as it's not needed, but keeping stderr in case there is an error

		$output = shell_exec($command);
		// If the command has an output (that's bad news)

		# Return the last bit of the output, which is generally the error message
		if($output){
			throw new \Exception(trim($output));
		}

		# Remove the original webp file
		unlink($file['tmp_name']);

		# Add the jpg suffix to the tmp name (to reflect the conversion to PNG)
		$file['tmp_name'] .= ".{$ext}";

		/**
		 * Data fetched by filesize() is "statcached",
		 * we need to clear it as the same file name
		 * has a different size now.
		 */
		$file['type'] = "image/{$ext}";
		$file['mime_type'] = "image/{$ext}";
		$file['ext'] = "{$ext}";
		$file['name'] .= ".{$ext}";
		$file['md5'] = md5_file($file['tmp_name']);
		$file['size'] = filesize($file['tmp_name']);

		# Attach the original back
		$file['original'][__FUNCTION__] = $original;
	}

	/**
	 * If an image or PDF is monochrome, or
	 * uses less than 300 unique colours, this
	 * method will make a copy of the document
	 * as a PNG and increase the contrast
	 * and reduce the grays to increase legibility.
	 *
	 * @param array $file
	 *
	 * @return void
	 * @throws \ImagickException
	 */
	public static function bw(array &$file): void
	{
		# We only need to do this once
		if(Convert::hasAlreadyBeenProcessed($file, __FUNCTION__)){
			return;
		}

		# Open ImageMagik
		$imagick = new \Imagick();

		# Load PDF slightly differently
		if($file['pdf_info']){
			//if the file is a PDF

			# We're not interested in PDFs that have text
			if($file['pdf_info']['text']){
				// They may be monochrome but not because of poor scanning
				$file['original'][__FUNCTION__] = "Has text.";
				return;
			}

			# Ensure file only has one page
			if($file['pdf_info']['pages'] != 1){
				// If the PDF has more than one pages, we're not doing anything with the doc (at the moment)
				$file['original'][__FUNCTION__] = "Has {$file['pdf_info']['pages']} pages.";
				# Pencils down
				return;
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

		try{
			# Read the first (and only) page
			$imagick->readImage($filename);
		}
			# Catch errors
		catch (\ImagickException $e){
			// If there is an error, there isn't much we can do here
			return;
		}


		$number_of_colours = $imagick->getImageColors();

		# We're only looking for monochrome (or close to monochrome) images
		if($number_of_colours > 300){
			//Image has more than 300 unique colours

			# Get Saturation levels (returns a number between 0 and 1), the higher the number the more saturation
			$saturation = shell_exec("magick {$filename} -colorspace HCL -format %[fx:maxima.g] info:");
			// Faffy, but the best way I've seen so far
			// @link https://legacy.imagemagick.org/discourse-server/viewtopic.php?t=34020
			/**
			 * Note: If an image that is mostly white except for a few high-saturation
			 * pixels will be, on average, nearly white, so we prefer to find the maximum
			 * saturation (fx:maxima) instead of the mean (fx:mean).
			 */

			# We'll accept saturation levels of up to 25% as black/white images
			if($saturation > 0.25){
				// If the max level of saturation is higher than 25%

				# Clear any cache
				$imagick->clear();

				# Explain why we're stopping
				$file['original'][__FUNCTION__] = "Has {$number_of_colours} colours and saturation of ".str::percent($saturation, 3).".";

				# Pencils down
				return;
			}
		}

		# Keep a copy of the original, rename the file to avoid it being overwritten
		$original = Convert::makeCopy($file, __FUNCTION__);

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

		# Trim away white space
		self::trim($imagick, .5);
		// Setting this higher, can result in the whole image disappearing

		# The output of this process is a lossless PNG image
		$imagick->setImageFormat("png");

		# Set the background to white
		$imagick->setImageBackgroundColor('#FFFFFF');
		// Doesn't seem to make a difference, but is added just in case, doesn't cost anything in terms of time

		# Flatten image (this will prevent pages with transparencies to go black)
		$imagick = $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
		// Needs to be in place for the trimming to work

		# Set the image curves
		if(!self::setCurve($imagick, 100, 200)){
			// If we can't set a curve, we'll set levels instead
			self::setLevels($imagick, .2, 6, 1);
		}

		# Store the page as a PNG
		$imagick->writeImage("png:" . $file['tmp_name']);

		# And we're done with ImageMagick
		$imagick->clear();

		# Set the new metadata
		clearstatcache();
		/**
		 * Data fetched by filesize() is "statcached",
		 * we need to clear it as the same file name
		 * has a different size now.
		 */
		$file['type'] = "image/png";
		$file['mime_type'] = "image/png";
		$file['ext'] = "png";
		$file['name'] .= ".png";
		$file['md5'] = md5_file($file['tmp_name']);
		$file['size'] = filesize($file['tmp_name']);

		# And it's not a PDF anymore, so let's get rid of this
		unset($file['pdf_info']);

		# Attach the original back (potentially with the PDF info)
		$file['original'][__FUNCTION__] = $original;
	}


	/**
	 * If a file is a PDF, checks to see if either width or height are bigger than the
	 * max number of allowed inches. If so, will rescale down the page to the max inch size.
	 *
	 * @param array          $file
	 * @param float|int|null $max_in     The default is set to 17 inches, the max width/height that Microsoft Cognitive
	 *                                   Services accepts.
	 *
	 * @return void
	 * @throws BadRequest
	 * @link https://urmaul.com/blog/imagick-filters-comparison/
	 */
	public static function big(array &$file, ?float $max_in = 17): void
	{
		# We only need to do this once
		if(Convert::hasAlreadyBeenProcessed($file, __FUNCTION__)){
			return;
		}
		# Ensure file is PDF
		if(!$file['pdf_info']){
			//if the file isn't a PDF
			return;
		}

		# Ensure file is too big
		if(($file['pdf_info']['page_height_in'] <= $max_in) && ($file['pdf_info']['page_width_in'] <= $max_in)){
			//If neither the width nor height are bigger than the max width/height in inches
			return;
		}

		# Keep a copy of the original, rename the file to avoid it being overwritten
		$original = Convert::makeCopy($file, __FUNCTION__);

		$max_points = $max_in * 72;

		$cmd = "gs ".
			"-o {$file['tmp_name']}-{$max_points} ".
			"-sDEVICE=pdfwrite ".
			"-dPDFSETTINGS=/prepress ".
			"-dCompatibilityLevel=1.4 ".
			"-dFIXEDMEDIA ".
			"-dPDFFitPage ".
			"-dDEVICEWIDTHPOINTS={$max_points} ".
			"-dDEVICEHEIGHTPOINTS={$max_points} ".
			"-f {$file['tmp_name']}";

		if(!str::exec($cmd, $output)){
			throw new \Exception("Unable to resize PDF file {$file['tmp_name']}: " . implode("<br>", $output) . "<br> After running <code>{$cmd}</code>. Please try again.");
		}

		# Remove the input tmp file
		unlink($file['tmp_name']);

		# Rename the output tmp file to the input tmp file
		rename("{$file['tmp_name']}-{$max_points}", $file['tmp_name']);

		# Clear the cache
		clearstatcache();
		/**
		 * Data fetched by filesize() is "statcached",
		 * we need to clear it as the same file name
		 * has a different size now.
		 */

		# Set the new metadata
		Doc::addPDFMetadata($file);
		$file['md5'] = md5_file($file['tmp_name']);
		$file['size'] = filesize($file['tmp_name']);

		# Attach the original back
		$file['original'][__FUNCTION__] = $original;
	}


	/**
	 * If the PDF is a single page PDF, the page is converted to a JPG.
	 * This is because Azure is not very good at reading PDFs, and it's
	 * much better to have a JPG to work with.
	 *
	 * @param array    $file
	 * @param int|null $resolution
	 * @param int|null $quality
	 *
	 * @return void
	 */
	public static function single(array &$file, ?int $resolution = 200, ?int $quality = 50): void
	{
		# We only need to do this once
		if(Convert::hasAlreadyBeenProcessed($file, __FUNCTION__)){
			return;
		}
		# Ensure file is PDF
		if(!$file['pdf_info']){
			//if the file isn't a PDF
			return;
		}

		# Ensure the PDF only has one page
		if($file['pdf_info']['pages'] != 1){
			// If the PDF has more than one page
			return;
		}

		# Keep a copy of the original, rename the file to avoid it being overwritten
		$original = Convert::makeCopy($file, __FUNCTION__);

		# Write command to convert the PDF to JPG using pdftoppm
		$cmd = "pdftoppm -jpeg -r {$resolution} -jpegopt quality={$quality} {$file['tmp_name']} {$file['tmp_name']}";

		# Execute the command
		shell_exec($cmd);

		# Remove the original PDF
		unlink($file['tmp_name']);

		# Add the pdftoppm suffix (to reflect the conversion to JPG)
		$file['tmp_name'] .= '-1.jpg';

		$file['md5'] = md5_file($file['tmp_name']);
		$file['size'] = filesize($file['tmp_name']);
		$file['type'] = mime_content_type($file['tmp_name']);
		$file['ext'] = pathinfo($file['tmp_name'], PATHINFO_EXTENSION);

		# Attach the original back
		$file['original'][__FUNCTION__] = $original;
	}

	/**
	 * The fuzz value is based on the quantum range,
	 * which is usually 65,535 in these kinds of
	 * images.
	 *
	 * @link https://www.php.net/manual/en/imagick.getquantumrange.php
	 * @link https://stackoverflow.com/questions/27356055/trimming-extra-white-background-from-image-using-imagemagick-in-php
	 *
	 * @param \Imagick   $imagick
	 * @param float|null $fuzz Float between 0 and 1 describing the threshold with 0 being no threshold
	 *
	 * @throws \ImagickException
	 */
	private static function trim(\Imagick &$imagick, ?float $fuzz = 0.6): void
	{
		# Get the quantum number
		$quantum = $imagick->getQuantumRange();
		$quantum = $quantum['quantumRangeLong'];

		$imagick->trimImage($fuzz * $quantum);
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
	private static function setLevels(\Imagick &$imagick, ?float $black = 0.2, int $gamma = 6, ?float $white = 1): void
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
	 * Requires gnuplot.
	 *
	 * @param \Imagick $imagick
	 * @param int      $x1
	 * @param int      $y1
	 * @param int      $max We're using the Photoshop scale of 255, but ImageMagick uses a 0-1 float.
	 *
	 * @return bool
	 * @throws \ImagickException
	 */
	private static function setCurve(\Imagick &$imagick, int $x1 = 100, int $y1 = 200, int $max = 255): bool
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

	/**
	 * Takes the file array, makes a copy of the tmp file with
	 * a method suffix, changes the value of the tmp_file key,
	 * and return the original file array, that doesn't have any
	 * of the changes.
	 *
	 * @param array  $file
	 * @param string $method
	 *
	 * @return array
	 */
	private static function makeCopy(array &$file, string $method): array
	{
		# Take a copy of the original array
		$original = $file;

		# Generate the copy name
		$new_tmp_name = $file['tmp_name'] . Convert::GLUE . $method;

		# Make a copy of the file with the new name
		copy($file['tmp_name'], $new_tmp_name);

		# Change the value in the array
		$file['tmp_name'] = $new_tmp_name;

		# Remove this key to avoid inception
		unset($original['original']);

		# Return the original array
		return $original;
	}

	private static function hasAlreadyBeenProcessed(array &$file, string $method): bool
	{
		# If the file has already been processed by this method
		if($file['original'] && key_exists($method, $file['original'])){
			return true;
		}

		# Load the key, even if we don't end up filling it
		$file['original'][$method] = NULL;
		// This way we can catch it if we happen to run the method again

		return false;
	}

	/**
	 * Converts a WOFF2 file to TTF so that it can be read by the fc-list command.
	 * Isn't a document in the colloquial sense, but it's a file that needs to be
	 * converted to be read by the fc-list command.
	 *
	 * @param string $path
	 *
	 * @return string|null
	 */
	public static function convertWoffToTtf(string $path): ?string
	{
		# Convert the WOFF2 file to TTF so that it can be read by the fc-list command
		$cmd = "woff2_decompress {$path} 2>&1";
		if(shell_exec($cmd)){
			return NULL;
		}

		# Rename the woff2 file to ttf
		$path = str_replace(".woff2", ".ttf", $path);

		# Ensure the .ttf suffix is on the path
		if(substr($path, -4) != ".ttf"){
			$path .= ".ttf";
		}

		# Return the new path
		return $path;
	}
}