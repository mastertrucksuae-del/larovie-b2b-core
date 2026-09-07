<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Converts admin-uploaded artwork to WebP.
 *
 * Every image the storefront serves from local storage goes through here:
 * WebP is 25-35% smaller than the equivalent JPEG/PNG at the same quality,
 * which is a direct Core Web Vitals win, and the storefront already declares
 * intrinsic dimensions so the smaller payload costs nothing in layout stability.
 *
 * Two deliberate non-goals:
 *
 * - **KYC trade licences are never processed here.** They live on the private
 *   disk, are frequently PDFs, and are legal evidence — re-encoding them would
 *   both fail and destroy the original.
 * - **Anything GD cannot decode passes through untouched** (SVG being the
 *   obvious one). A vector logo is already smaller than any raster it could be
 *   turned into, and silently rasterising it would be a downgrade.
 */
class ImageProcessor
{
    /**
     * Downscale ceiling. The largest slot on the storefront is the hero at
     * ~1400 CSS px; 1600 leaves headroom for high-DPI without storing originals
     * straight off a phone camera.
     */
    public const MAX_WIDTH = 1600;

    public const QUALITY = 82;

    /**
     * Refuse to decode anything above this many pixels.
     *
     * GD holds a decoded image as ~4 bytes per pixel, so a 50MP "image bomb"
     * would be 200MB of memory and take the request down. 40MP is far larger
     * than any legitimate logo or hero shot.
     */
    private const MAX_PIXELS = 40_000_000;

    /** Source formats worth converting. Anything else is stored as uploaded. */
    private const CONVERTIBLE = [
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/pjpeg' => 'imagecreatefromjpeg',
        'image/png' => 'imagecreatefrompng',
        'image/gif' => 'imagecreatefromgif',
        'image/bmp' => 'imagecreatefrombmp',
        'image/webp' => 'imagecreatefromwebp',
    ];

    /**
     * Store an upload on the public disk, as WebP where possible.
     *
     * @return string the stored path, relative to the disk
     */
    public static function storeAsWebp(UploadedFile $file, string $directory): string
    {
        $converted = self::toWebp($file);

        if ($converted === null) {
            // Not convertible (or conversion failed): keep the original rather
            // than losing the upload.
            return $file->store($directory, 'public');
        }

        $path = trim($directory, '/').'/'.Str::random(40).'.webp';
        Storage::disk('public')->put($path, $converted);

        return $path;
    }

    /**
     * The WebP bytes for an upload, or null when it should be left alone.
     */
    public static function toWebp(UploadedFile $file): ?string
    {
        $scratch = null;

        $mime = strtolower((string) $file->getMimeType());
        $decoder = self::CONVERTIBLE[$mime] ?? null;

        if ($decoder === null || ! function_exists('imagewebp')) {
            return null;
        }

        // Livewire hands over a TemporaryUploadedFile whose realpath can be
        // empty depending on the temp disk, so fall back to spooling it out.
        $path = self::readablePath($file, $scratch);

        if ($path === null) {
            return null;
        }

        try {
            $dimensions = @getimagesize($path);

            if ($dimensions === false || ($dimensions[0] * $dimensions[1]) > self::MAX_PIXELS) {
                return null;
            }

            $image = @$decoder($path);

            if ($image === false) {
                return null;
            }

            $image = self::downscale($image);

            // PNG and WebP logos are routinely transparent; without these the
            // background comes out black.
            imagealphablending($image, false);
            imagesavealpha($image, true);

            ob_start();
            $ok = imagewebp($image, null, self::QUALITY);
            $bytes = (string) ob_get_clean();
            imagedestroy($image);

            return ($ok && $bytes !== '') ? $bytes : null;
        } catch (\Throwable $e) {
            // A malformed upload should not take the admin panel down; the
            // original is stored instead.
            Log::warning('WebP conversion failed, storing original.', [
                'mime' => $mime,
                'error' => $e->getMessage(),
            ]);

            return null;
        } finally {
            if ($scratch !== null && is_file($scratch)) {
                @unlink($scratch);
            }
        }
    }

    /**
     * A path GD can open, spooling the upload to disk when it has no realpath.
     */
    private static function readablePath(UploadedFile $file, ?string &$scratch): ?string
    {
        $path = $file->getRealPath();

        if (is_string($path) && $path !== '' && is_file($path)) {
            return $path;
        }

        try {
            $scratch = tempnam(sys_get_temp_dir(), 'larovie-img');

            if ($scratch === false) {
                return null;
            }

            file_put_contents($scratch, $file->get());

            return $scratch;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param  \GdImage  $image */
    private static function downscale(\GdImage $image): \GdImage
    {
        $width = imagesx($image);

        if ($width <= self::MAX_WIDTH) {
            return $image;
        }

        $height = (int) round(imagesy($image) * (self::MAX_WIDTH / $width));
        $resized = imagescale($image, self::MAX_WIDTH, $height);

        if ($resized === false) {
            return $image;
        }

        imagedestroy($image);

        return $resized;
    }
}
