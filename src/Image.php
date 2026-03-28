<?php declare(strict_types=1);
/*
 * This file is part of Aplus Framework Image Library.
 *
 * (c) Natan Felles <natanfelles@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Framework\Image;

use GdImage;
use InvalidArgumentException;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use LogicException;
use RuntimeException;

/**
 * Class Image.
 *
 * @package image
 */
class Image implements \JsonSerializable, \Stringable
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
     * GdImage instance.
     */
    protected GdImage $instance;
    /**
     * The image quality/compression level.
     *
     * 0 to 9 on PNG, default is 6. 0 to 100 on JPEG, default is 75.
     * Null to update to the default when getQuality is called.
     *
     * @see Image::getQuality()
     */
    protected ?int $quality = null;

    /**
     * Image constructor.
     *
     * @param string $filename Path to the image file.
     * Acceptable types are: AVIF, GIF, JPEG and PNG
     *
     * @throws InvalidArgumentException for invalid file
     * @throws RuntimeException for unsupported image type of could not get image info
     */
    public function __construct(string $filename)
    {
        $realpath = \realpath($filename);
        if ($realpath === false || !\is_file($realpath) || !\is_readable($realpath)) {
            throw new InvalidArgumentException('File does not exists or is not readable: ' . $filename);
        }
        $this->filename = $realpath;
        $info = \getimagesize($this->filename);
        if ($info === false) {
            throw new RuntimeException(
                'Could not get image info from the given filename: ' . $this->filename
            );
        }
        if (!(\imagetypes() & $info[2])) {
            throw new RuntimeException('Unsupported image type: ' . $info[2]);
        }
        $this->setType($info[2]);
        $this->setMime($info['mime']);
        $instance = match ($this->getType()) {
            \IMAGETYPE_PNG => \imagecreatefrompng($this->filename),
            \IMAGETYPE_JPEG => \imagecreatefromjpeg($this->filename),
            \IMAGETYPE_GIF => \imagecreatefromgif($this->filename),
            \IMAGETYPE_AVIF => \imagecreatefromavif($this->filename),
            default => throw new RuntimeException('Image type is not acceptable: ' . $this->getType()),
        };
        if (!$instance instanceof GdImage) {
            throw new RuntimeException(
                "Image of type '{$this->getType()}' does not returned a GdImage instance"
            );
        }
        $this->setInstance($instance);
    }

    public function __toString() : string
    {
        return $this->getDataUrl();
    }

    /**
     * Gets the GdImage instance.
     *
     * @return GdImage GdImage instance
     */
    #[Pure]
    public function getInstance() : GdImage
    {
        return $this->instance;
    }

    /**
     * Sets the GdImage instance.
     *
     * @param GdImage $instance GdImage instance
     *
     * @return static
     */
    public function setInstance(GdImage $instance) : static
    {
        $this->instance = $instance;
        return $this;
    }

    /**
     * Gets the image quality/compression level.
     *
     * @return int|null An integer for AVIF, JPEG and PNG types or null for GIF
     */
    public function getQuality() : ?int
    {
        if ($this->quality === null) {
            if ($this->isType(\IMAGETYPE_PNG)) {
                $this->quality = 6;
            } elseif ($this->isType(\IMAGETYPE_JPEG)) {
                $this->quality = 75;
            } elseif ($this->isType(\IMAGETYPE_AVIF)) {
                $this->quality = 52;
            }
        }
        return $this->quality;
    }

    /**
     * Sets the image quality/compression level.
     *
     * @param int $quality The quality/compression level
     *
     * @throws LogicException when trying to set a quality value for a GIF image
     * @throws InvalidArgumentException if the image type is PNG and the value
     * is not between 0 and 9, if the image type is JPEG and the value is not
     * between 0 and 100 or if the image type is AVIF and the value is not
     * between 0 and 100
     *
     * @return static
     */
    public function setQuality(int $quality) : static
    {
        if ($this->isType(\IMAGETYPE_GIF)) {
            throw new LogicException(
                'GIF images does not receive a quality value'
            );
        }
        if ($this->isType(\IMAGETYPE_PNG) && ($quality < 0 || $quality > 9)) {
            throw new InvalidArgumentException(
                'PNG images must receive a quality value between 0 and 9, ' . $quality . ' given'
            );
        }
        if ($this->isType(\IMAGETYPE_JPEG) && ($quality < 0 || $quality > 100)) {
            throw new InvalidArgumentException(
                'JPEG images must receive a quality value between 0 and 100, ' . $quality . ' given'
            );
        }
        if ($this->isType(\IMAGETYPE_AVIF) && ($quality < 0 || $quality > 100)) {
            throw new InvalidArgumentException(
                'AVIF images must receive a quality value between 0 and 100, ' . $quality . ' given'
            );
        }
        $this->quality = $quality;
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
        $resolution = \imageresolution($this->getInstance());
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
     * Sets the image resolution.
     *
     * @param int $horizontal The horizontal resolution in DPI
     * @param int $vertical The vertical resolution in DPI
     *
     * @throws RuntimeException for image could not to set resolution
     *
     * @return static
     */
    public function setResolution(int $horizontal = 96, int $vertical = 96) : static
    {
        $set = \imageresolution($this->getInstance(), $horizontal, $vertical);
        if ($set === false) {
            throw new RuntimeException('Image could not to set resolution');
        }
        return $this;
    }

    /**
     * Gets the image height.
     *
     * @return int
     */
    #[Pure]
    public function getHeight() : int
    {
        return \imagesy($this->getInstance());
    }

    /**
     * Gets the image width.
     *
     * @return int
     */
    #[Pure]
    public function getWidth() : int
    {
        return \imagesx($this->getInstance());
    }

    /**
     * Gets the file extension for image type.
     *
     * @return false|string a string with the extension corresponding to the
     * given image type or false on fail
     */
    #[Pure]
    public function getExtension() : false | string
    {
        return \image_type_to_extension($this->getType());
    }

    /**
     * Gets the image MIME type.
     *
     * @return string
     */
    #[Pure]
    public function getMime() : string
    {
        return $this->mime;
    }

    protected function setMime(string $mime) : static
    {
        $this->mime = $mime;
        return $this;
    }

    /**
     * Gets the image type.
     *
     * @return int
     */
    public function getType() : int
    {
        return $this->type;
    }

    protected function setType(int $type) : static
    {
        $this->type = $type;
        return $this;
    }

    public function isType(int $type) : bool
    {
        return $this->getType() === $type;
    }

    /**
     * Creates a new Image based in the current instance.
     *
     * @param int|string $type The new Image type
     * @param string $filename The filename where the new Image will be placed
     */
    public function create(int | string $type, string $filename) : Image
    {
        $created = match ($type) {
            \IMAGETYPE_PNG, 'png' => \imagepng($this->getInstance(), $filename),
            \IMAGETYPE_JPEG, 'jpeg' => \imagejpeg($this->getInstance(), $filename),
            \IMAGETYPE_GIF, 'gif' => \imagegif($this->getInstance(), $filename),
            \IMAGETYPE_AVIF, 'avif' => \imageavif($this->getInstance(), $filename),
            default => false,
        };
        if($created === false) {
            throw new RuntimeException('Image could not be created');
        }
        return new Image($filename);
    }

    /**
     * Saves the image contents to a given filename.
     *
     * @param string|null $filename Optional filename or null to use the original
     *
     * @return bool
     */
    public function save(?string $filename = null) : bool
    {
        $filename ??= $this->filename;
        return match ($this->getType()) {
            \IMAGETYPE_PNG => \imagepng($this->getInstance(), $filename, $this->getQuality()),
            \IMAGETYPE_JPEG => \imagejpeg($this->getInstance(), $filename, $this->getQuality()),
            \IMAGETYPE_GIF => \imagegif($this->getInstance(), $filename),
            \IMAGETYPE_AVIF => \imageavif($this->getInstance(), $filename, $this->getQuality()),
            default => false,
        };
    }

    /**
     * Sends the image contents to the output buffer.
     *
     * @return bool
     */
    public function send() : bool
    {
        if ($this->hasAlpha()) {
            \imagesavealpha($this->getInstance(), true);
        }
        return match ($this->getType()) {
            \IMAGETYPE_PNG => \imagepng($this->getInstance(), null, $this->getQuality()),
            \IMAGETYPE_JPEG => \imagejpeg($this->getInstance(), null, $this->getQuality()),
            \IMAGETYPE_GIF => \imagegif($this->getInstance()),
            \IMAGETYPE_AVIF => \imageavif($this->getInstance(), null, $this->getQuality()),
            default => false,
        };
    }

    /**
     * Renders the image contents.
     *
     * @throws RuntimeException for image could not be rendered
     *
     * @return string The image contents
     */
    public function render() : string
    {
        \ob_start();
        $status = $this->send();
        $contents = \ob_get_clean();
        if ($status === false || $contents === false) {
            throw new RuntimeException('Image could not be rendered');
        }
        return $contents;
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
     * @return static
     */
    public function crop(int $width, int $height, int $marginLeft = 0, int $marginTop = 0) : static
    {
        $crop = \imagecrop($this->getInstance(), [
            'x' => $marginLeft,
            'y' => $marginTop,
            'width' => $width,
            'height' => $height,
        ]);
        if ($crop === false) {
            throw new RuntimeException('Image could not to crop');
        }
        $this->setInstance($crop);
        return $this;
    }

    /**
     * Flips the image.
     *
     * @param string $direction Allowed values are: h or horizontal. v or vertical. b or both.
     *
     * @throws InvalidArgumentException for invalid image flip direction
     * @throws RuntimeException for image could not to flip
     *
     * @return static
     */
    public function flip(string $direction = 'horizontal') : static
    {
        $direction = match ($direction) {
            'h', 'horizontal' => \IMG_FLIP_HORIZONTAL,
            'v', 'vertical' => \IMG_FLIP_VERTICAL,
            'b', 'both' => \IMG_FLIP_BOTH,
            default => throw new InvalidArgumentException('Invalid image flip direction: ' . $direction),
        };
        $flip = \imageflip($this->getInstance(), $direction);
        if ($flip === false) {
            throw new RuntimeException('Image could not to flip');
        }
        return $this;
    }

    /**
     * Applies a filter to the image.
     *
     * @param int $type IMG_FILTER_* constants
     * @param int ...$arguments Arguments for the filter type
     *
     * @see https://www.php.net/manual/en/function.imagefilter.php
     *
     * @throws RuntimeException for image could not apply the filter
     *
     * @return static
     */
    public function filter(int $type, int ...$arguments) : static
    {
        $filtered = \imagefilter($this->getInstance(), $type, ...$arguments);
        if ($filtered === false) {
            throw new RuntimeException('Image could not apply the filter');
        }
        return $this;
    }

    /**
     * Flattens the image.
     *
     * Replaces transparency with an RGB color.
     *
     * @param int $red
     * @param int $green
     * @param int $blue
     *
     * @throws RuntimeException for could not create a true color image, could
     * not allocate a color or image could not to flatten
     *
     * @return static
     */
    public function flatten(int $red = 255, int $green = 255, int $blue = 255) : static
    {
        \imagesavealpha($this->getInstance(), false);
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
            $this->getInstance(),
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
        $this->setInstance($image);
        return $this;
    }

    /**
     * Sets the image opacity level.
     *
     * @param int $opacity Opacity percentage: from 0 to 100
     *
     * @return static
     */
    public function opacity(int $opacity = 100) : static
    {
        if ($opacity < 0 || $opacity > 100) {
            throw new InvalidArgumentException(
                'Opacity percentage must be between 0 and 100, ' . $opacity . ' given'
            );
        }
        if ($opacity === 100) {
            \imagealphablending($this->getInstance(), true);
            return $this;
        }
        $opacity = (int) \round(\abs(($opacity * 127 / 100) - 127));
        \imagelayereffect($this->getInstance(), \IMG_EFFECT_OVERLAY);
        $color = \imagecolorallocatealpha($this->getInstance(), 127, 127, 127, $opacity);
        if ($color === false) {
            throw new RuntimeException('Image could not allocate a color');
        }
        \imagefilledrectangle(
            $this->getInstance(),
            0,
            0,
            $this->getWidth(),
            $this->getHeight(),
            $color
        );
        \imagesavealpha($this->getInstance(), true);
        \imagealphablending($this->getInstance(), false);
        return $this;
    }

    /**
     * Rotates the image with a given angle.
     *
     * @param float $angle Rotation angle, in degrees. Clockwise direction.
     *
     * @throws RuntimeException for image could not allocate a color or could not rotate
     *
     * @return static
     */
    public function rotate(float $angle) : static
    {
        $background = $this->allocateBackground();
        if ($background === false) {
            throw new RuntimeException('Image could not allocate a color');
        }
        $rotate = \imagerotate($this->getInstance(), -1 * $angle, $background);
        if ($rotate === false) {
            throw new RuntimeException('Image could not to rotate');
        }
        $this->setInstance($rotate);
        return $this;
    }

    protected function allocateBackground() : false | int
    {
        if ($this->hasAlpha()) {
            \imagealphablending($this->getInstance(), false);
            \imagesavealpha($this->getInstance(), true);
            return \imagecolorallocatealpha($this->getInstance(), 0, 0, 0, 127);
        }
        return \imagecolorallocate($this->getInstance(), 255, 255, 255);
    }

    public function hasAlpha() : bool
    {
        return \in_array(
            $this->getType(),
            [
                \IMAGETYPE_PNG,
                \IMAGETYPE_GIF,
                \IMAGETYPE_AVIF,
            ],
            true
        );
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
     * @return static
     */
    public function scale(int $width, int $height = -1) : static
    {
        $scale = \imagescale($this->getInstance(), $width, $height);
        if ($scale === false) {
            throw new RuntimeException('Image could not to scale');
        }
        $this->setInstance($scale);
        return $this;
    }

    /**
     * Adds a watermark to the image.
     *
     * @param Image $watermark The image to use as watermark
     * @param int $horizontalPosition Horizontal position
     * @param int $verticalPosition Vertical position
     *
     * @throws RuntimeException for image could not to create watermark
     *
     * @return static
     */
    public function watermark(
        Image $watermark,
        int $horizontalPosition = 0,
        int $verticalPosition = 0
    ) : static {
        if ($horizontalPosition < 0) {
            $horizontalPosition = $this->getWidth()
                - (-1 * $horizontalPosition + $watermark->getWidth());
        }
        if ($verticalPosition < 0) {
            $verticalPosition = $this->getHeight()
                - (-1 * $verticalPosition + $watermark->getHeight());
        }
        $copied = \imagecopy(
            $this->getInstance(),
            $watermark->getInstance(),
            $horizontalPosition,
            $verticalPosition,
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
     * Allow embed the image contents in a document.
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/Data_URIs
     * @see https://datatracker.ietf.org/doc/html/rfc2397
     *
     * @return string The image "data" URL
     */
    public function getDataUrl() : string
    {
        return 'data:' . $this->getMime() . ';base64,' . \base64_encode($this->render());
    }

    /**
     * @return string
     */
    public function jsonSerialize() : string
    {
        return $this->getDataUrl();
    }

    /**
     * Indicates if a given filename has an acceptable image type.
     *
     * @param string $filename
     *
     * @return bool
     */
    public static function isAcceptable(string $filename) : bool
    {
        $filename = \realpath($filename);
        if ($filename === false || !\is_file($filename) || !\is_readable($filename)) {
            return false;
        }
        $info = \getimagesize($filename);
        if ($info === false) {
            return false;
        }
        return match ($info[2]) {
            \IMAGETYPE_PNG, \IMAGETYPE_JPEG, \IMAGETYPE_GIF, \IMAGETYPE_AVIF => true,
            default => false,
        };
    }
}
