<?php namespace Framework\Image;

/**
 * Class Image.
 */
class Image
{
	/**
	 * Path to the image file.
	 *
	 * @var string
	 */
	protected $filename;
	/**
	 * Image type. One of IMAGETYPE_* constants.
	 *
	 * @var int
	 */
	protected $type;
	/**
	 * MIME type.
	 *
	 * @var string
	 */
	protected $mime;
	/**
	 * GD resource.
	 *
	 * @var resource
	 */
	protected $resource;

	/**
	 * Image constructor.
	 *
	 * @param string $filename path to the image file
	 */
	public function __construct(string $filename)
	{
		if ( ! \is_readable($filename)) {
			throw new \Exception('File "' . $filename . '" does not exists or is not readable.');
		}
		$this->filename = $filename;
		$info = \getimagesize($this->filename);
		if ($info === false) {
			throw new \Exception('Could not get info of the given image filename');
		}
		if ( ! (\imagetypes() & $info[2])) {
			throw new \Exception('Unsupported image type.');
		}
		//\var_dump($info);exit;
		$this->type = $info[2];
		$this->mime = $info['mime'];
		switch ($this->type) {
			case \IMAGETYPE_PNG:
				$this->resource = \imagecreatefrompng($filename);
				break;
			case \IMAGETYPE_JPEG:
				$this->resource = \imagecreatefromjpeg($filename);
				break;
			case \IMAGETYPE_GIF:
				$this->resource = \imagecreatefromgif($filename);
				break;
			default:
				throw new \Exception('Image type "' . $this->type . '" is not available.');
		}
	}

	public function __destruct()
	{
		$this->destroy();
	}

	/*
	// USE ONLY IN CREATED - NOT IN MANIPULATED ONLY
	public function create(int $width, int $height)
	{
		$this->resource = \imagecreate($width, $height);
	}

	public function color(int $red, int $green, int $blue, int $alpha = null)
	{
		if ($alpha === null)
		{
			$color = \imagecolorallocate($this->resource, $red, $green, $blue);
		}
		else
		{
			$color = \imagecolorallocatealpha($this->resource, $red, $green, $blue, $alpha);
		}

		// The first time a color is set to the background
		// After return the color identifier
		if ($this->background === null)
		{
			return $this;
		}

		return $color;
	}

	public function string($font, $x, $y, $string, $color)
	{
		\imagestring($this->resource, $font, $x, $y, $string, $color);
	}

	public function getFont($filename)
	{
		return \imagepsloadfont($filename);
	}
	*/
	public function save(string $filename = null, int $quality = null) : bool
	{
		$filename = $filename ?? $this->filename;
		switch ($this->type) {
			case \IMAGETYPE_PNG:
				return \imagepng($this->resource, $filename, $quality ?? 6);
				break;
			case \IMAGETYPE_JPEG:
				return \imagejpeg($this->resource, $filename, $quality ?? 75);
				break;
			case \IMAGETYPE_GIF:
				return \imagegif($this->resource, $filename);
				break;
			default:
				throw new \Exception('Image type "' . $this->type . '" is not available.');
		}
	}

	/**
	 * Renders the image output.
	 *
	 * @param int|null $quality The quality/compression level. 0 to 9 on PNG, default is 6. 0 to
	 *                          100 on JPEG, default is 75. Leave null to use the default.
	 *
	 * @return false|string TRUE on success or FALSE on failure
	 */
	public function render(int $quality = null)
	{
		\ob_start();
		switch ($this->type) {
			case \IMAGETYPE_PNG:
				\imagepng($this->resource, null, $quality ?? 6);
				break;
			case \IMAGETYPE_JPEG:
				\imagejpeg($this->resource, null, $quality ?? 75);
				break;
			case \IMAGETYPE_GIF:
				\imagegif($this->resource, null);
				break;
			default:
				throw new \Exception('Image type "' . $this->type . '" is not available.');
		}
		return \ob_get_clean();
	}

	/**
	 * Get image height.
	 *
	 * @return int
	 */
	public function getHeight() : int
	{
		return \imagesy($this->resource);
	}

	/**
	 * Get image width.
	 *
	 * @return int
	 */
	public function getWidth() : int
	{
		return \imagesx($this->resource);
	}

	/**
	 * Get file extension for image type.
	 *
	 * @param bool $include_dot whether to prepend a dot to the extension or not
	 *
	 * @return string a string with the extension corresponding to the given image type
	 */
	public function getExtension(bool $include_dot = true) : string
	{
		return \image_type_to_extension($this->type, $include_dot);
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
	 * @return $this
	 */
	public function flip(string $direction = 'horizontal')
	{
		switch ($direction) {
			case 'h':
			case 'horizontal':
				$direction = \IMG_FLIP_HORIZONTAL;
				break;
			case 'v':
			case 'vertical':
				$direction = \IMG_FLIP_VERTICAL;
				break;
			case 'b':
			case 'both':
				$direction = \IMG_FLIP_BOTH;
				break;
			default:
				throw new \Exception('Invalid image flip direction "' . $direction . '"');
				break;
		}
		$flip = \imageflip($this->resource, $direction);
		if ($flip === false) {
			throw new \Exception('Was not possible to flip.');
		}
		return $this;
	}

	/**
	 * Crops the image.
	 *
	 * @param int $width       width in pixels
	 * @param int $height      height in pixels
	 * @param int $margin_left margin left in pixels
	 * @param int $margin_top  margin top in pixels
	 *
	 * @return $this
	 */
	public function crop(int $width, int $height, int $margin_left = 0, int $margin_top = 0)
	{
		$crop = \imagecrop($this->resource, [
			'x' => $margin_left,
			'y' => $margin_top,
			'width' => $width,
			'height' => $height,
		]);
		if ($crop === false) {
			throw new \Exception('Was not possible to crop.');
		}
		$this->resource = $crop;
		return $this;
	}

	/**
	 * Scales the image.
	 *
	 * @param int $width  width in pixels
	 * @param int $height Height in pixels. Use -1 to use a proportional height based on the width.
	 *
	 * @return $this
	 */
	public function scale(int $width, int $height = -1)
	{
		$scale = \imagescale($this->resource, $width, $height);
		if ($scale === false) {
			throw new \Exception('Was not possible to scale.');
		}
		$this->resource = $scale;
		return $this;
	}

	/**
	 * Rotates the image with a given angle.
	 *
	 * @param float $angle Rotation angle, in degrees. Clockwise direction.
	 *
	 * @return $this
	 */
	public function rotate(float $angle)
	{
		if (\in_array($this->type, [\IMAGETYPE_PNG, \IMAGETYPE_GIF])) {
			\imagealphablending($this->resource, false);
			\imagesavealpha($this->resource, true);
			$background = \imagecolorallocatealpha($this->resource, 0, 0, 0, 127);
		} else {
			$background = \imagecolorallocate($this->resource, 255, 255, 255);
		}
		$rotate = \imagerotate($this->resource, -1 * $angle, $background);
		if ($rotate === false) {
			throw new \Exception('Was not possible to rotate.');
		}
		$this->resource = $rotate;
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
	 * @return $this
	 */
	public function flatten(int $red = 255, int $green = 255, int $blue = 255)
	{
		\imagesavealpha($this->resource, false);
		$image = \imagecreatetruecolor($this->getWidth(), $this->getHeight());
		\imagefilledrectangle(
			$image,
			0,
			0,
			$this->getWidth(),
			$this->getHeight(),
			\imagecolorallocate($image, $red, $green, $blue)
		);
		$copied = \imagecopy(
			$image,
			$this->resource,
			0,
			0,
			0,
			0,
			$this->getWidth(),
			$this->getHeight()
		);
		if ($copied === false) {
			throw new \Exception('Was not possible to flatten.');
		}
		$this->resource = $image;
		return $this;
	}

	/**
	 * Sets the image resolution.
	 *
	 * @param int $horizontal the horizontal resolution in DPI
	 * @param int $vertical   the vertical resolution in DPI
	 *
	 * @return $this
	 */
	public function setResolution(int $horizontal = 96, int $vertical = 96)
	{
		$set = \imageresolution($this->resource, $horizontal, $vertical);
		if ($set === false) {
			throw new \Exception('Was not possible to set resolution.');
		}
		return $this;
	}

	/**
	 * Applies a filter to the image.
	 *
	 * @param int   $type         IMG_FILTER_* constants
	 * @param mixed ...$arguments Arguments for the filter type.
	 *
	 * @see https://secure.php.net/manual/en/function.imagefilter.php
	 *
	 * @return $this
	 */
	public function filter(int $type, ...$arguments)
	{
		$filtered = \imagefilter($this->resource, $type, ...$arguments);
		if ($filtered === false) {
			throw new \Exception('Was not possible to filter.');
		}
		return $this;
	}

	/**
	 * Gets the image GD resource.
	 *
	 * @return resource GD resource
	 */
	public function getResource()
	{
		return $this->resource;
	}

	/**
	 * Sets the image GD resource.
	 *
	 * @param resource $resource GD resource
	 *
	 * @return $this
	 */
	public function setResource($resource)
	{
		if (($type = \get_resource_type($resource)) !== 'gd') {
			throw new \Exception('Image resource must be of type "gd". "' . $type . '" given.');
		}
		$this->resource = $resource;
		return $this;
	}

	/**
	 * Adds a watermark to the image.
	 *
	 * @param \Framework\Image\Image $watermark the image to use as watermark
	 * @param int                    $x         horizontal position
	 * @param int                    $y         vertical position
	 *
	 * @return $this
	 */
	public function watermark(Image $watermark, int $x = 0, int $y = 0)
	{
		if ($x < 0) {
			$x = $this->getWidth() - (-1 * $x + $watermark->getWidth());
		}
		if ($y < 0) {
			$y = $this->getHeight() - (-1 * $y + $watermark->getHeight());
		}
		$copied = \imagecopy(
			$this->resource,
			$watermark->getResource(),
			$x,
			$y,
			0,
			0,
			$watermark->getWidth(),
			$watermark->getHeight()
		);
		if ($copied === false) {
			throw new \Exception('Was not possible to create watermark.');
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
			$opacity = \round(\abs(($opacity * 127 / 100) - 127));
			\imagelayereffect($this->resource, \IMG_EFFECT_OVERLAY);
			\imagefilledrectangle(
				$this->resource,
				0,
				0,
				$this->getWidth(),
				$this->getHeight(),
				\imagecolorallocatealpha($this->resource, 127, 127, 127, $opacity)
			);
			\imagesavealpha($this->resource, true);
			\imagealphablending($this->resource, false);
		} else {
			\imagealphablending($this->resource, true);
		}
		return $this;
	}

	/**
	 * Gets the image resolution.
	 *
	 * @return array array containing two keys, horizontal and vertical, with integers
	 */
	public function getResolution() : array
	{
		$resolution = \imageresolution($this->resource);
		return [
			'horizontal' => $resolution[0],
			'vertical' => $resolution[1],
		];
	}

	/**
	 * Destroys the image resource.
	 *
	 * @return bool
	 */
	public function destroy() : bool
	{
		if (\is_resource($this->resource)) {
			return \imagedestroy($this->resource);
		}
		return true;
	}
}
