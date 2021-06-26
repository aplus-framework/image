<?php namespace Tests\Image;

use Framework\Image\Image;
use PHPUnit\Framework\TestCase;

final class ImageTest extends TestCase
{
	protected Image $image;

	public function setup() : void
	{
		$this->image = new Image(__DIR__ . '/Support/tree.png');
	}

	public function testSave() : void
	{
		$filename = '/tmp/--image.png';
		self::assertFalse(\is_file($filename));
		$this->image->save($filename);
		self::assertTrue(\is_file($filename));
		\unlink($filename);
	}

	public function testIsAcceptable() : void
	{
		self::assertTrue(
			Image::isAcceptable(__DIR__ . '/Support/tree.png')
		);
		self::assertFalse(
			Image::isAcceptable(__FILE__)
		);
		self::assertFalse(
			Image::isAcceptable('/tmp/unknown')
		);
	}

	public function testHeight() : void
	{
		self::assertSame(920, $this->image->getHeight());
	}

	public function testWidth() : void
	{
		self::assertSame(700, $this->image->getWidth());
	}

	public function testExtension() : void
	{
		self::assertSame('.png', $this->image->getExtension());
	}

	public function testMime() : void
	{
		self::assertSame('image/png', $this->image->getMime());
	}

	public function testResolution() : void
	{
		self::assertSame([
			'horizontal' => 300,
			'vertical' => 300,
		], $this->image->getResolution());
		$this->image->setResolution();
		self::assertSame([
			'horizontal' => 96,
			'vertical' => 96,
		], $this->image->getResolution());
		//$this->expectException(\Exception::class);
		//$this->image->setResolution(0);
	}

	public function testDestroy() : void
	{
		self::assertTrue($this->image->destroy());
		self::assertTrue($this->image->destroy());
	}

	public function testInstance() : void
	{
		self::assertInstanceOf(\GdImage::class, $this->image->getInstance());
		$instance = \imagecreatefrompng(__DIR__ . '/Support/tree.png');
		self::assertNotSame($instance, $this->image->getInstance());
		$this->image->setInstance($instance); // @phpstan-ignore-line
		self::assertSame($instance, $this->image->getInstance());
		$this->expectException(\TypeError::class);
		$instance = \fopen(__FILE__, 'rb');
		$this->image->setInstance($instance); // @phpstan-ignore-line
	}

	public function testFileIsNotImage() : void
	{
		$this->expectException(\RuntimeException::class);
		new Image(__FILE__);
	}

	public function testOpacity() : void
	{
		$this->image->opacity(60);
		//\file_put_contents(__DIR__ . '/Support/tree-opacity.png', $this->image->render());
		self::assertStringEqualsFile(
			__DIR__ . '/Support/tree-opacity.png',
			$this->image->render()
		);
	}

	public function testOpacityGreatLevel() : void
	{
		$this->image->opacity();
		self::assertStringEqualsFile(
			__DIR__ . '/Support/tree-opacity-g.png',
			$this->image->render()
		);
	}

	public function testOpacityInvalidLevel() : void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage(
			'Opacity percentage must be between 0 and 100, 120 given'
		);
		$this->image->opacity(120);
	}

	public function testScale() : void
	{
		$this->image->scale(80, 80);
		\file_put_contents(__DIR__ . '/Support/tree-scale.png', $this->image->render());
		self::assertStringEqualsFile(
			__DIR__ . '/Support/tree-scale.png',
			$this->image->render()
		);
	}

	public function testRotate() : void
	{
		$this->image->rotate(45);
		self::assertStringEqualsFile(
			__DIR__ . '/Support/tree-rotate.png',
			$this->image->render()
		);
	}

	public function testFlipHorizontal() : void
	{
		$this->image->flip();
		self::assertStringEqualsFile(
			__DIR__ . '/Support/tree-flip-h.png',
			$this->image->render()
		);
	}

	public function testFlipVertical() : void
	{
		$this->image->flip('v');
		self::assertStringEqualsFile(
			__DIR__ . '/Support/tree-flip-v.png',
			$this->image->render()
		);
	}

	public function testFlipBoth() : void
	{
		$this->image->flip('b');
		self::assertStringEqualsFile(
			__DIR__ . '/Support/tree-flip-b.png',
			$this->image->render()
		);
	}

	public function testCrop() : void
	{
		$this->image->crop(200, 200, 100, 100);
		self::assertStringEqualsFile(
			__DIR__ . '/Support/tree-crop.png',
			$this->image->render()
		);
	}

	public function testFilter() : void
	{
		$this->image->filter(\IMG_FILTER_NEGATE);
		//\file_put_contents(__DIR__ . '/Support/tree-filter.png', $this->image->render());
		self::assertStringEqualsFile(
			__DIR__ . '/Support/tree-filter.png',
			$this->image->render()
		);
	}

	public function testFlatten() : void
	{
		$this->image->flatten(128, 0, 128);
		self::assertStringEqualsFile(
			__DIR__ . '/Support/tree-flatten.png',
			$this->image->render()
		);
	}

	public function testWatermark() : void
	{
		$watermark = new Image(__DIR__ . '/Support/tree.png');
		$watermark->scale(100);
		$this->image->watermark($watermark, -10, -10);
		self::assertStringEqualsFile(
			__DIR__ . '/Support/tree-watermark.png',
			$this->image->render()
		);
	}

	public function testJson() : void
	{
		self::assertStringStartsWith(
			'"data:image\/png;base64,',
			\json_encode($this->image) ?: ''
		);
	}

	public function testFileNotReadable() : void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('File does not exists or is not readable: /tmp/foo');
		new Image('/tmp/foo');
	}

	public function testUnsupportedType() : void
	{
		$file = __DIR__ . '/Support/tree.bmp';
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Image type is not acceptable: 6');
		new Image($file);
	}

	public function testJpgType() : void
	{
		$file = __DIR__ . '/Support/tree.jpg';
		$image = new Image($file);
		self::assertTrue($image->save());
		$image->render();
	}

	public function testGifType() : void
	{
		$file = __DIR__ . '/Support/tree.gif';
		$image = new Image($file);
		self::assertTrue($image->save());
		$image->render();
	}
}
