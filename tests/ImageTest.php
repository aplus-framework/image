<?php namespace Tests\Image;

use Framework\Image\Image;
use PHPUnit\Framework\TestCase;

class ImageTest extends TestCase
{
	/**
	 * @var Image
	 */
	protected $image;

	public function setup() : void
	{
		$this->image = new Image(__DIR__ . '/Support/tree.png');
	}

	public function testInstance()
	{
		$this->assertInstanceOf(
			'Framework\Image\Image',
			$this->image
		);
	}
}
