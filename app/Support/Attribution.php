<?php

namespace App\Support;

/**
 * First-touch attribution captured on the visitor's first page view and held
 * in the session until an inquiry is submitted (P0 #5). The CaptureAttribution
 * middleware writes it; actions read it back here.
 */
class Attribution
{
    public const SESSION_KEY = '_attribution';

    public const KEYS = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'landing_page', 'referrer',
    ];

    /** @return array<string, ?string> */
    public static function all(): array
    {
        return session(self::SESSION_KEY, []);
    }

    public static function get(string $key): ?string
    {
        return static::all()[$key] ?? null;
    }
}
