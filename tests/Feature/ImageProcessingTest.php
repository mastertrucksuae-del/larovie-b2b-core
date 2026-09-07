<?php

namespace Tests\Feature;

use App\Support\ImageProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Admin uploads are converted to WebP.
 */
class ImageProcessingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /** A real PNG on disk — GD has to actually decode it, so a stub will not do. */
    private function png(int $width = 800, int $height = 600, bool $transparent = false): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);

        if ($transparent) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        } else {
            imagefill($image, 0, 0, imagecolorallocate($image, 199, 95, 123));
        }

        $path = tempnam(sys_get_temp_dir(), 'src').'.png';
        imagepng($image, $path);
        imagedestroy($image);

        return new UploadedFile($path, 'logo.png', 'image/png', null, true);
    }

    public function test_an_uploaded_png_is_stored_as_webp(): void
    {
        $path = ImageProcessor::storeAsWebp($this->png(), 'branding');

        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists($path);

        $bytes = Storage::disk('public')->get($path);
        $this->assertSame('image/webp', (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes));
    }

    public function test_the_webp_is_smaller_than_the_png_it_replaced(): void
    {
        $source = $this->png(1200, 900);
        $original = filesize($source->getRealPath());

        $path = ImageProcessor::storeAsWebp($source, 'branding');

        $this->assertLessThan(
            $original,
            strlen(Storage::disk('public')->get($path)),
            'Conversion should shrink the file, otherwise it is not worth doing.'
        );
    }

    public function test_oversized_uploads_are_downscaled(): void
    {
        $path = ImageProcessor::storeAsWebp($this->png(2400, 1200), 'homepage');

        $info = getimagesizefromstring(Storage::disk('public')->get($path));

        $this->assertSame(ImageProcessor::MAX_WIDTH, $info[0]);
        $this->assertSame(800, $info[1], 'Aspect ratio should be preserved.');
    }

    public function test_an_image_within_the_limit_keeps_its_dimensions(): void
    {
        $path = ImageProcessor::storeAsWebp($this->png(640, 480), 'branding');

        $info = getimagesizefromstring(Storage::disk('public')->get($path));

        $this->assertSame([640, 480], [$info[0], $info[1]]);
    }

    public function test_transparency_survives_the_conversion(): void
    {
        // Without imagesavealpha a transparent logo comes out on a black box.
        $path = ImageProcessor::storeAsWebp($this->png(100, 100, transparent: true), 'brands');

        $image = imagecreatefromstring(Storage::disk('public')->get($path));
        $alpha = (imagecolorat($image, 50, 50) >> 24) & 0x7F;
        imagedestroy($image);

        $this->assertGreaterThan(100, $alpha, 'The transparent pixel should still be transparent.');
    }

    public function test_a_vector_logo_is_stored_untouched(): void
    {
        // Rasterising an SVG would make it bigger and blurrier.
        $svg = UploadedFile::fake()->createWithContent(
            'logo.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>'
        );

        $path = ImageProcessor::storeAsWebp($svg, 'branding');

        $this->assertStringEndsNotWith('.webp', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_a_file_that_is_not_an_image_is_never_mangled(): void
    {
        $pdf = UploadedFile::fake()->create('licence.pdf', 10, 'application/pdf');

        $this->assertNull(ImageProcessor::toWebp($pdf));
    }
}
