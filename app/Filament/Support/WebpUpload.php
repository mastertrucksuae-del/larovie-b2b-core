<?php

namespace App\Filament\Support;

use App\Support\ImageProcessor;
use Filament\Forms\Components\FileUpload;
use Illuminate\Http\UploadedFile;

/**
 * A file upload that stores WebP.
 *
 * One factory rather than the same `saveUploadedFileUsing` closure copied across
 * five forms: the conversion rules are a single decision, and a field that
 * quietly skipped them would put an unoptimised megabyte on the storefront.
 */
class WebpUpload
{
    /** Formats an admin may upload. SVG is allowed through and stored as-is. */
    private const ACCEPTED = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/bmp',
        'image/webp',
        'image/svg+xml',
    ];

    /**
     * The largest upload PHP will actually accept, in kilobytes.
     *
     * Advertising a limit the server cannot honour is worse than a small limit:
     * PHP discards an oversized upload before any code runs, so the request
     * arrives with no file at all, Livewire never returns a temporary file, and
     * the widget sits on "Waiting for size" forever with no error. Deriving the
     * field limit from the real ini values turns that silent hang into an
     * immediate, honest "file too large" message.
     */
    public static function serverLimitKb(): int
    {
        $limits = array_filter([
            self::iniBytes('upload_max_filesize'),
            self::iniBytes('post_max_size'),
        ]);

        // post_max_size has to cover the rest of the form body too, so leave a
        // little headroom rather than sizing right up to the wall.
        $bytes = $limits === [] ? 2 * 1024 * 1024 : (int) (min($limits) * 0.9);

        return max(256, min((int) floor($bytes / 1024), 12 * 1024));
    }

    public static function humanLimit(): string
    {
        $kb = self::serverLimitKb();

        return $kb >= 1024
            ? rtrim(rtrim(number_format($kb / 1024, 1), '0'), '.').' MB'
            : $kb.' KB';
    }

    /** Parse a php.ini shorthand size ("2M", "512K") into bytes. */
    private static function iniBytes(string $key): ?int
    {
        $raw = trim((string) ini_get($key));

        if ($raw === '' || $raw === '-1' || $raw === '0') {
            return null; // unlimited, or not set
        }

        $unit = strtolower(substr($raw, -1));
        $value = (int) $raw;

        return match ($unit) {
            'g' => $value * 1024 ** 3,
            'm' => $value * 1024 ** 2,
            'k' => $value * 1024,
            default => $value,
        };
    }

    public static function make(string $name, string $directory): FileUpload
    {
        return FileUpload::make($name)
            ->image()
            ->disk('public')
            ->directory($directory)
            ->acceptedFileTypes(self::ACCEPTED)
            ->maxSize(self::serverLimitKb())
            ->helperText(sprintf(
                'Converted to WebP and resized automatically for faster page loads. Maximum %s per file.',
                self::humanLimit()
            ))
            ->saveUploadedFileUsing(
                fn (UploadedFile $file) => ImageProcessor::storeAsWebp($file, $directory)
            );
    }
}
