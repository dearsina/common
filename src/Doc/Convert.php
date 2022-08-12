<?php

namespace App\Common\Doc;

use App\Common\str;

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
		Convert::heic($file);
		Convert::bw($file);
		Convert::big($file);
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
			if($method_file){
				$originals[$method] = $method_file;
			}
		}

		return $originals;
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

		# Open ImageMagik
		$imagick = new \Imagick();

		# Read the image
		$imagick->readImage($original['tmp_name']);

		# Set image quality
		if($quality){
			//if the quality variable is set
			$imagick->setImageCompressionQuality($quality);
		}

		# Set image format
		$imagick->setImageFormat("jpeg");

		# Store the HEIC as a JPG
		$imagick->writeImage("jpg:" . $file['tmp_name']);

		# And we're done with ImageMagick
		$imagick->clear();

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

		# Read the first (and only) page
		$imagick->readImage($filename);

		$number_of_colours = $imagick->getImageColors();

		# We're only looking for monochrome (or close to monochrome) images
		if($number_of_colours > 300){
			//Image has more than 300 unique colours

			# Get Saturation levels (returns a number between 0 and 1), the higher the number the more saturation
			$saturation = shell_exec("magick {$filename} -colorspace HCL -format %[fx:mean.g] info:");
			// Faffy, but the best way I've seen so far
			// @link https://legacy.imagemagick.org/discourse-server/viewtopic.php?t=34020

			# We'll accept saturation levels of up to 2% as black/white images
			if($saturation > 0.02){
				// If the level of saturation is higher than 2%

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

		# The output of this process is a lossless PNG image
		$imagick->setImageFormat("png");

		# Set the background to white
		$imagick->setImageBackgroundColor('#FFFFFF');
		// Doesn't seem to make a difference, but is added just in case, doesn't cost anything in terms of time

		# Flatten image (this will prevent pages with transparencies to go black)
		$imagick = $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
		// Needs to be in place for the trimming to work

		# Aggressively trim away white space
		self::trim($imagick, .6);

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
	 * max number of allowed inches. If so, will rescale down the image using the given
	 * resolution, save each page using the quality JPG and the put each JPG page
	 * back together into a single PDF, whose name will be passed back.
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
	 * @return void
	 * @throws \ImagickException
	 * @link https://urmaul.com/blog/imagick-filters-comparison/
	 */
	public static function big(array &$file, ?float $max_in = 17, ?int $resolution = 144, ?int $quality = 50): void
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

		# Load the page images
		$pdf = new \Imagick($images);

		# Set the format of the output to be PDF
		$pdf->setImageFormat('pdf');

		# Write all the images as individual pages in the PDF
		$pdf->writeImages($file['tmp_name'], true);

		# And we're done with ImageMagick
		$imagick->clear();

		# Remove all the temporary page JPGs
		array_map("unlink", $images);

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

	private static function makeCopy(array $file, string $method): array
	{
		# Generate the copy name
		$new_tmp_name = $file['tmp_name'] . Convert::GLUE . $method;

		# Make a copy of the file with the new name
		copy($file['tmp_name'], $new_tmp_name);

		# Change the value in the array
		$file['tmp_name'] = $new_tmp_name;

		# Remove this key to avoid inception
		unset($file['original']);

		# Return the copied array
		return $file;
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
}