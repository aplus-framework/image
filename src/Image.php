<?php namespace Framework\Image;

use GdImage;
use InvalidArgumentException;
use JetBrains\PhpStorm\ArrayShape;
use RuntimeException;

/**
 * Class Image.
 */
class Image implements \JsonSerializable
{
	/**
	 * Path to the image file.
	 */
	protected string $filename;
	/**
	 * Image type. One of IMAGETYPE_* constants.
	 */
	protected int $type;
	/**
	 * MIME type.
	 */
	protected string $mime;
	/**
	 * GD instance.
	 *
	 * @var GdImage
	 */
	protected GdImage $instance;

	/**
	 * Image constructor.
	 *
	 * @param string $filename Path to the image file
	 *
	 * @throws InvalidArgumentException for invalid file
	 * @throws RuntimeException for unsupported image type of could not get image info
	 */
	public function __construct(string $filename)
	{
		if ( ! \is_readable($filename)) {
			throw new InvalidArgumentException('File does not exists or is not readable: ' . $filename);
		}
		$this->filename = $filename;
		$info = \getimagesize($this->filename);
		if ($info === false) {
			throw new RuntimeException(
				'Could not get info of the given image filename: ' . $this->filename
			);
		}
		if ( ! (\imagetypes() & $info[2])) {
			throw new RuntimeException('Unsupported image type: ' . $info[2]);
		}
		$this->type = $info[2];
		$this->mime = $info['mime'];
		$instance = match ($this->type) {
			\IMAGETYPE_PNG => \imagecreatefrompng($filename),
			\IMAGETYPE_JPEG => \imagecreatefromjpeg($filename),
			\IMAGETYPE_GIF => \imagecreatefromgif($filename),
			default => throw new RuntimeException('Image type is not available: ' . $this->type),
		};
		if ( ! $instance instanceof GdImage) {
			throw new RuntimeException(
				"Image of type '{$this->type}' does not returned a GdImage instance"
			);
		}
		$this->instance = $instance;
	}

	public function __destruct()
	{
		$this->destroy();
	}

	/**
	 * Save the Image to a given filename.
	 *
	 * @param string|null $filename Optional filename or null to use the original
	 * @param int|null $quality The quality/compression level.
	 * 0 to 9 on PNG, default is 6. 0 to 100 on JPEG, default is 75.
	 * Leave null to use the default.
	 *
	 * @throws RuntimeException for image type not available
	 *
	 * @return bool
	 */
	public function save(string $filename = null, int $quality = null) : bool
	{
		$filename = $filename ?? $this->filename;
		return match ($this->type) {
			\IMAGETYPE_PNG => \imagepng($this->instance, $filename, $quality ?? 6),
			\IMAGETYPE_JPEG => \imagejpeg($this->instance, $filename, $quality ?? 75),
			\IMAGETYPE_GIF => \imagegif($this->instance, $filename),
			default => throw new RuntimeException('Image type is not available: ' . $this->type),
		};
	}

	/**
	 * Renders the image output.
	 *
	 * @param int|null $quality The quality/compression level.
	 * 0 to 9 on PNG, default is 6. 0 to 100 on JPEG, default is 75.
	 * Leave null to use the default.
	 *
	 * @throws RuntimeException for image type not available or image could not
	 * be rendered
	 *
	 * @return string The image contents
	 */
	public function render(int $quality = null) : string
	{
		\ob_start();
		$status = $this->send($quality);
		$contents = \ob_get_clean();
		if ($status === false || $contents === false) {
			throw new RuntimeException('Image could not be rendered');
		}
		return $contents;
	}

	/**
	 * Output the image to the browser.
	 *
	 * @param int|null $quality The quality/compression level.
	 * 0 to 9 on PNG, default is 6. 0 to 100 on JPEG, default is 75.
	 * Leave null to use the default.
	 *
	 * @throws RuntimeException for image type not available
	 *
	 * @return bool
	 */
	public function send(int $quality = null) : bool
	{
		// @phpstan-ignore-next-line
		return match ($this->type) {
			\IMAGETYPE_PNG => \imagepng($this->instance, null, $quality ?? 6),
			\IMAGETYPE_JPEG => \imagejpeg($this->instance, null, $quality ?? 75),
			\IMAGETYPE_GIF => \imagegif($this->instance),
			default => throw new RuntimeException('Image type is not available: ' . $this->type),
		};
	}

	/**
	 * Get image height.
	 *
	 * @return int
	 */
	public function getHeight() : int
	{
		return \imagesy($this->instance);
	}

	/**
	 * Get image width.
	 *
	 * @return int
	 */
	public function getWidth() : int
	{
		return \imagesx($this->instance);
	}

	/**
	 * Get file extension for image type.
	 *
	 * @return false|string a string with the extension corresponding to the
	 * given image type or false on fail
	 */
	public function getExtension() : string | false
	{
		return \image_type_to_extension($this->type);
	}

	/**
	 * Gets the image MIME type.
	 *
	 * @return string
	 */
	public function getMime() : string
	{
		return $this->mime;
	}

	/**
	 * Flips the image.
	 *
	 * @param string $direction Allowed values are: h or horizontal. v or vertical. b or both.
	 *
	 * @throws InvalidArgumentException for invalid image flip direction
	 * @throws RuntimeException for image could not to flip
	 *
	 * @return $this
	 */
	public function flip(string $direction = 'horizontal')
	{
		$direction = match ($direction) {
			'h', 'horizontal' => \IMG_FLIP_HORIZONTAL,
			'v', 'vertical' => \IMG_FLIP_VERTICAL,
			'b', 'both' => \IMG_FLIP_BOTH,
			default => throw new InvalidArgumentException('Invalid image flip direction: ' . $direction),
		};
		$flip = \imageflip($this->instance, $direction);
		if ($flip === false) {
			throw new RuntimeException('Image could not to flip');
		}
		return $this;
	}

	/**
	 * Crops the image.
	 *
	 * @param int $width Width in pixels
	 * @param int $height Height in pixels
	 * @param int $marginLeft Margin left in pixels
	 * @param int $marginTop Margin top in pixels
	 *
	 * @throws RuntimeException for image could not to crop
	 *
	 * @return $this
	 */
	public function crop(int $width, int $height, int $marginLeft = 0, int $marginTop = 0)
	{
		$crop = \imagecrop($this->instance, [
			'x' => $marginLeft,
			'y' => $marginTop,
			'width' => $width,
			'height' => $height,
		]);
		if ($crop === false) {
			throw new RuntimeException('Image could not to crop');
		}
		$this->instance = $crop;
		return $this;
	}

	/**
	 * Scales the image.
	 *
	 * @param int $width Width in pixels
	 * @param int $height Height in pixels. Use -1 to use a proportional height
	 * based on the width.
	 *
	 * @throws RuntimeException for image could not to scale
	 *
	 * @return $this
	 */
	public function scale(int $width, int $height = -1)
	{
		$scale = \imagescale($this->instance, $width, $height);
		if ($scale === false) {
			throw new RuntimeException('Image could not to scale');
		}
		$this->instance = $scale;
		return $this;
	}

	/**
	 * Rotates the image with a given angle.
	 *
	 * @param float $angle Rotation angle, in degrees. Clockwise direction.
	 *
	 * @throws RuntimeException for image could not allocate a color or could not rotate
	 *
	 * @return $this
	 */
	public function rotate(float $angle)
	{
		if (\in_array($this->type, [\IMAGETYPE_PNG, \IMAGETYPE_GIF], true)) {
			\imagealphablending($this->instance, false);
			\imagesavealpha($this->instance, true);
			$background = \imagecolorallocatealpha($this->instance, 0, 0, 0, 127);
		} else {
			$background = \imagecolorallocate($this->instance, 255, 255, 255);
		}
		if ($background === false) {
			throw new RuntimeException('Image could not allocate a color');
		}
		$rotate = \imagerotate($this->instance, -1 * $angle, $background);
		if ($rotate === false) {
			throw new RuntimeException('Image could not to rotate');
		}
		$this->instance = $rotate;
		return $this;
	}

	/**
	 * Flattens the image.
	 *
	 * Replaces transparency with a RGB color.
	 *
	 * @param int $red
	 * @param int $green
	 * @param int $blue
	 *
	 * @throws RuntimeException for could not create a true color image, could
	 * not allocate a color or image could not to flatten
	 *
	 * @return $this
	 */
	public function flatten(int $red = 255, int $green = 255, int $blue = 255)
	{
		\imagesavealpha($this->instance, false);
		$image = \imagecreatetruecolor($this->getWidth(), $this->getHeight());
		if ($image === false) {
			throw new RuntimeException('Could not create a true color image');
		}
		$color = \imagecolorallocate($image, $red, $green, $blue);
		if ($color === false) {
			throw new RuntimeException('Image could not allocate a color');
		}
		\imagefilledrectangle(
			$image,
			0,
			0,
			$this->getWidth(),
			$this->getHeight(),
			$color
		);
		$copied = \imagecopy(
			$image,
			$this->instance,
			0,
			0,
			0,
			0,
			$this->getWidth(),
			$this->getHeight()
		);
		if ($copied === false) {
			throw new RuntimeException('Image could not to flatten');
		}
		$this->instance = $image;
		return $this;
	}

	/**
	 * Sets the image resolution.
	 *
	 * @param int $horizontal The horizontal resolution in DPI
	 * @param int $vertical The vertical resolution in DPI
	 *
	 * @throws RuntimeException for image could not to set resolution
	 *
	 * @return $this
	 */
	public function setResolution(int $horizontal = 96, int $vertical = 96)
	{
		$set = \imageresolution($this->instance, $horizontal, $vertical);
		if ($set === false) {
			throw new RuntimeException('Image could not to set resolution');
		}
		return $this;
	}

	/**
	 * Applies a filter to the image.
	 *
	 * @param int $type IMG_FILTER_* constants
	 * @param int ...$arguments Arguments for the filter type
	 *
	 * @see https://secure.php.net/manual/en/function.imagefilter.php
	 *
	 * @throws RuntimeException for image could not apply the filter
	 *
	 * @return $this
	 */
	public function filter(int $type, int ...$arguments)
	{
		$filtered = \imagefilter($this->instance, $type, ...$arguments);
		if ($filtered === false) {
			throw new RuntimeException('Image could not apply the filter');
		}
		return $this;
	}

	/**
	 * Gets the image GD instance.
	 *
	 * @return GdImage GD instance
	 */
	public function getInstance() : GdImage
	{
		return $this->instance;
	}

	/**
	 * Sets the image GD instance.
	 *
	 * @param GdImage $instance GD instance
	 *
	 * @return $this
	 */
	public function setInstance(GdImage $instance)
	{
		$this->instance = $instance;
		return $this;
	}

	/**
	 * Adds a watermark to the image.
	 *
	 * @param Image $watermark The image to use as watermark
	 * @param int $marginLeft Horizontal position
	 * @param int $marginTop Vertical position
	 *
	 * @throws RuntimeException for image could not to create watermark
	 *
	 * @return $this
	 */
	public function watermark(
		Image $watermark,
		int $marginLeft = 0,
		int $marginTop = 0
	) {
		if ($marginLeft < 0) {
			$marginLeft = $this->getWidth()
				- (-1 * $marginLeft + $watermark->getWidth());
		}
		if ($marginTop < 0) {
			$marginTop = $this->getHeight()
				- (-1 * $marginTop + $watermark->getHeight());
		}
		$copied = \imagecopy(
			$this->instance,
			$watermark->getInstance(),
			$marginLeft,
			$marginTop,
			0,
			0,
			$watermark->getWidth(),
			$watermark->getHeight()
		);
		if ($copied === false) {
			throw new RuntimeException('Image could not to create watermark');
		}
		return $this;
	}

	/**
	 * Sets the image opacity level.
	 *
	 * @param int $opacity 0 to 100
	 *
	 * @return $this
	 */
	public function opacity(int $opacity = 100)
	{
		if ($opacity < 100) {
			$opacity = (int) \round(\abs(($opacity * 127 / 100) - 127));
			\imagelayereffect($this->instance, \IMG_EFFECT_OVERLAY);
			$color = \imagecolorallocatealpha($this->instance, 127, 127, 127, $opacity);
			if ($color === false) {
				throw new RuntimeException('Image could not allocate a color');
			}
			\imagefilledrectangle(
				$this->instance,
				0,
				0,
				$this->getWidth(),
				$this->getHeight(),
				$color
			);
			\imagesavealpha($this->instance, true);
			\imagealphablending($this->instance, false);
		} else {
			\imagealphablending($this->instance, true);
		}
		return $this;
	}

	/**
	 * Gets the image resolution.
	 *
	 * @throws RuntimeException for image could not get resolution
	 *
	 * @return array<string,int> Returns an array containing two keys, horizontal and
	 * vertical, with integers as values
	 */
	#[ArrayShape(['horizontal' => 'int', 'vertical' => 'int'])]
	public function getResolution() : array
	{
		$resolution = \imageresolution($this->instance);
		if ($resolution === false) {
			throw new RuntimeException('Image could not to get resolution');
		}
		return [
			'horizontal' => $resolution[0], // @phpstan-ignore-line
			// @phpstan-ignore-next-line
			'vertical' => $resolution[1],
		];
	}

	/**
	 * Destroys the image instance.
	 *
	 * @return bool
	 */
	public function destroy() : bool
	{
		return \imagedestroy($this->instance);
	}

	/**
	 * @throws RuntimeException for image cannot render because type not available
	 *
	 * @return string
	 */
	public function jsonSerialize() : string
	{
		return $this->getDataURI();
	}

	/**
	 * Allow embed the image contents in a document.
	 *
	 * @throws RuntimeException for image cannot render because type not available
	 *
	 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/Data_URIs
	 *
	 * @return string The image data URI
	 */
	public function getDataURI() : string
	{
		return 'data:' . $this->getMime() . ';base64,' . \base64_encode($this->render());
	}

	/**
	 * Indicates if a given filename is an acceptable image.
	 *
	 * @param string $filename
	 *
	 * @return bool
	 */
	public static function isImage(string $filename) : bool
	{
		if ( ! \is_readable($filename)) {
			return false;
		}
		$info = \getimagesize($filename);
		if ($info === false) {
			return false;
		}
		if ( ! (\imagetypes() & $info[2])) {
			return false;
		}
		return true;
	}
}
