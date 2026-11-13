<?php

namespace Tests\Unit;

use App\ImageCreator;
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Generates the image and returns the PNG bytes written on the output.
     */
    private function renderImage(): string
    {
        ob_start();
        (new ImageCreator([10, 20, 30], [40, 50, 60]))->createImage();
        return (string) ob_get_clean();
    }

    public function testCreateImageOutputsAPng(): void
    {
        $png = $this->renderImage();

        $this->assertStringStartsWith("\x89PNG", $png);
    }

    public function testImageHasTheExpectedSize(): void
    {
        $size = getimagesizefromstring($this->renderImage());

        $this->assertSame(600, $size[0]);
        $this->assertSame(200, $size[1]);
        $this->assertSame('image/png', $size['mime']);
    }

    public function testBorderUsesTheGivenColor(): void
    {
        $image = imagecreatefromstring($this->renderImage());
        $color = imagecolorsforindex($image, imagecolorat($image, 2, 2));

        $this->assertSame([10, 20, 30], [$color['red'], $color['green'], $color['blue']]);
    }
}
