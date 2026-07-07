<?php

namespace App\Support;

class BundleDetector
{
    /**
     * Words that mark a product as a bundle/kit/set rather than a solo item.
     * Matched case-insensitively on whole words across title, type and tags.
     */
    protected const PATTERN = '/\b(bundle|kit|duo|trio|combo|routine|set|pack|pcs?|\d+\s*-?\s*(piece|step)s?)\b/i';

    /**
     * Heuristic: does this product look like a multi-item bundle?
     *
     * @param  array<int,string>|null  $tags
     */
    public static function isBundle(?string $title, ?string $productType = null, ?array $tags = null): bool
    {
        $haystack = trim(implode(' ', array_filter([
            $title,
            $productType,
            $tags ? implode(' ', $tags) : null,
        ])));

        if ($haystack === '') {
            return false;
        }

        return (bool) preg_match(self::PATTERN, $haystack);
    }
}
