<?php namespace Tests\Image;

use Framework\Image\Image;
use PHPUnit\Framework\TestCase;

class ImageTest extends TestCase
{
	protected Image $image;

	public function setup() : void
	{
		$this->image = new Image(__DIR__ . '/Support/tree.png');
	}

	public function testSave()
	{
		$filename = '/tmp/--image.png';
		$this->assertFalse(\is_file($filename));
		$this->image->save($filename);
		$this->assertTrue(\is_file($filename));
		\unlink($filename);
	}

	public function testIsImage()
	{
		$this->assertTrue(
			Image::isImage(__DIR__ . '/Support/tree.png')
		);
		$this->assertFalse(
			Image::isImage(__FILE__)
		);
		$this->assertFalse(
			Image::isImage('/tmp/unkown')
		);
	}

	public function testHeight()
	{
		$this->assertEquals(920, $this->image->getHeight());
	}

	public function testWidth()
	{
		$this->assertEquals(700, $this->image->getWidth());
	}

	public function testExtension()
	{
		$this->assertEquals('.png', $this->image->getExtension());
		$this->assertEquals('png', $this->image->getExtension(false));
	}

	public function testMime()
	{
		$this->assertEquals('image/png', $this->image->getMime());
	}

	public function testResolution()
	{
		$this->assertEquals([
			'horizontal' => 300,
			'vertical' => 300,
		], $this->image->getResolution());
		$this->image->setResolution();
		$this->assertEquals([
			'horizontal' => 96,
			'vertical' => 96,
		], $this->image->getResolution());
		//$this->expectException(\Exception::class);
		//$this->image->setResolution(0);
	}

	public function testDestroy()
	{
		$this->assertTrue($this->image->destroy());
		$this->assertTrue($this->image->destroy());
	}

	public function testInstance()
	{
		$this->assertInstanceOf(\GdImage::class, $this->image->getInstance());
		$instance = \imagecreatefrompng(__DIR__ . '/Support/tree.png');
		$this->assertEquals($instance, $this->image->getInstance());
		$this->image->setInstance($instance);
		$this->assertEquals($instance, $this->image->getInstance());
		$this->expectException(\Exception::class);
		$instance = \fopen(__FILE__, 'rb');
		$this->image->setInstance($instance);
	}

	public function testFileIsNotImage()
	{
		$this->expectException(\RuntimeException::class);
		new Image(__FILE__);
	}

	public function testOpacity()
	{
		$this->image->opacity(60);
		//\file_put_contents(__DIR__ . '/Support/tree-opacity.png', $this->image->render());
		$this->assertStringEqualsFile(
			__DIR__ . '/Support/tree-opacity.png',
			$this->image->render()
		);
	}

	public function testOpacityGreatLevel()
	{
		$this->image->opacity(120);
		$this->assertStringEqualsFile(
			__DIR__ . '/Support/tree-opacity-g.png',
			$this->image->render()
		);
	}

	public function testScale()
	{
		$this->image->scale(80, 80);
		\file_put_contents(__DIR__ . '/Support/tree-scale.png', $this->image->render());
		$this->assertStringEqualsFile(
			__DIR__ . '/Support/tree-scale.png',
			$this->image->render()
		);
	}

	public function testRotate()
	{
		$this->image->rotate(45);
		$this->assertStringEqualsFile(
			__DIR__ . '/Support/tree-rotate.png',
			$this->image->render()
		);
	}

	public function testFlipHorizontal()
	{
		$this->image->flip();
		$this->assertStringEqualsFile(
			__DIR__ . '/Support/tree-flip-h.png',
			$this->image->render()
		);
	}

	public function testFlipVertical()
	{
		$this->image->flip('v');
		$this->assertStringEqualsFile(
			__DIR__ . '/Support/tree-flip-v.png',
			$this->image->render()
		);
	}

	public function testFlipBoth()
	{
		$this->image->flip('b');
		$this->assertStringEqualsFile(
			__DIR__ . '/Support/tree-flip-b.png',
			$this->image->render()
		);
	}

	public function testCrop()
	{
		$this->image->crop(200, 200, 100, 100);
		$this->assertStringEqualsFile(
			__DIR__ . '/Support/tree-crop.png',
			$this->image->render()
		);
	}

	public function testFilter()
	{
		$this->image->filter(\IMG_FILTER_NEGATE);
		//\file_put_contents(__DIR__ . '/Support/tree-filter.png', $this->image->render());
		$this->assertStringEqualsFile(
			__DIR__ . '/Support/tree-filter.png',
			$this->image->render()
		);
	}

	public function testFlaten()
	{
		$this->image->flatten(128, 0, 128);
		$this->assertStringEqualsFile(
			__DIR__ . '/Support/tree-flaten.png',
			$this->image->render()
		);
	}

	public function testWatermark()
	{
		$watermark = new Image(__DIR__ . '/Support/tree.png');
		$watermark->scale(100);
		$this->image->watermark($watermark, -10, -10);
		$this->assertStringEqualsFile(
			__DIR__ . '/Support/tree-watermark.png',
			$this->image->render()
		);
	}

	public function testJson()
	{
		$this->assertStringStartsWith(
			'"data:image\/png;base64,',
			\json_encode($this->image)
		);
	}

	public function testFileNotReadable()
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('File does not exists or is not readable: /tmp/foo');
		new Image('/tmp/foo');
	}

	public function testUnsupportedType()
	{
		$file = __DIR__ . '/Support/tree.bmp';
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Image type is not available: 6');
		new Image($file);
	}

	public function testJpgType()
	{
		$file = __DIR__ . '/Support/tree.jpg';
		$image = new Image($file);
		$this->assertTrue($image->save());
		$image->render();
	}

	public function testGifType()
	{
		$file = __DIR__ . '/Support/tree.gif';
		$image = new Image($file);
		$this->assertTrue($image->save());
		$image->render();
	}
}
