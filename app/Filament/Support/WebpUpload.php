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

    public static function make(string $name, string $directory): FileUpload
    {
        return FileUpload::make($name)
            ->image()
            ->disk('public')
            ->directory($directory)
            ->acceptedFileTypes(self::ACCEPTED)
            // 12MB in, but what lands on disk is a downscaled WebP.
            ->maxSize(12 * 1024)
            ->helperText('Converted to WebP and resized automatically for faster page loads.')
            ->saveUploadedFileUsing(
                fn (UploadedFile $file) => ImageProcessor::storeAsWebp($file, $directory)
            );
    }
}
