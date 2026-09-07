<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * Editable homepage copy.
 *
 * Every string on the homepage resolves through here: an admin override stored
 * on the settings row wins, and anything left blank falls back to the shipped
 * translation in `lang/{locale}/shop.php`. That keeps the page complete on a
 * fresh install, keeps both languages in sync by default, and means clearing a
 * field in the dashboard restores the original copy rather than blanking the
 * section.
 */
class HomeContent
{
    /**
     * The editable fields, grouped as they appear in the admin form.
     *
     * The value is the input type: 'text' for a single line, 'area' for a
     * paragraph. This map is the single source of truth — the Filament form and
     * the fallback lookup are both built from it, so a new line of homepage copy
     * becomes editable by adding it here and to the two lang files.
     *
     * @var array<string, array<string, string>>
     */
    public const GROUPS = [
        'Search engine listing' => [
            'home_title' => 'text',
            'home_meta_description' => 'area',
        ],
        'Announcement bar' => [
            'announcement' => 'text',
        ],
        'Hero' => [
            'hero_eyebrow' => 'text',
            'hero_title' => 'area',
            'hero_subtitle' => 'area',
            'hero_cta_browse' => 'text',
            'hero_cta_account' => 'text',
            'hero_image_alt' => 'text',
        ],
        'Trust strip' => [
            'trust_authentic' => 'text',
            'trust_registered' => 'text',
            'trust_licence' => 'text',
            'trust_support' => 'text',
            'trust_response' => 'text',
            'trust_secure' => 'text',
        ],
        'Section headings' => [
            'shop_by_category' => 'text',
            'view_all' => 'text',
            'featured_brands' => 'text',
            'view_all_brands' => 'text',
            'featured_products' => 'text',
            'why_partner' => 'text',
            'how_it_works' => 'text',
            'business_types' => 'text',
        ],
        'Why partner with us' => [
            'why_1_title' => 'text',
            'why_1_body' => 'area',
            'why_2_title' => 'text',
            'why_2_body' => 'area',
            'why_3_title' => 'text',
            'why_3_body' => 'area',
            'why_4_title' => 'text',
            'why_4_body' => 'area',
            'why_5_title' => 'text',
            'why_5_body' => 'area',
            'why_6_title' => 'text',
            'why_6_body' => 'area',
        ],
        'How ordering works' => [
            'step_1_title' => 'text',
            'step_1_body' => 'area',
            'step_2_title' => 'text',
            'step_2_body' => 'area',
            'step_3_title' => 'text',
            'step_3_body' => 'area',
            'step_4_title' => 'text',
            'step_4_body' => 'area',
        ],
        'Business types served' => [
            'type_retailers' => 'text',
            'type_pharmacies' => 'text',
            'type_salons' => 'text',
            'type_ecommerce' => 'text',
            'type_clinics' => 'text',
            'type_distributors' => 'text',
        ],
        'WhatsApp band' => [
            'whatsapp_title' => 'text',
            'whatsapp_body' => 'area',
            'whatsapp_cta' => 'text',
        ],
        'Closing call to action' => [
            'cta_title' => 'text',
            'cta_body' => 'area',
            'cta_contact' => 'text',
        ],
        'Product card labels' => [
            'view_details' => 'text',
            'add_to_inquiry' => 'text',
            'in_stock' => 'text',
            'limited_stock' => 'text',
            'out_of_stock' => 'text',
            'min_order' => 'text',
        ],
    ];

    /**
     * Sections that can be switched off, and whether they are on by default.
     *
     * Only the hero and trust strip are absent: they are the page's masthead and
     * always sit at the top. Everything below them, the account call-to-action
     * included, is the founder's to arrange.
     *
     * @var array<string, bool>
     */
    public const SECTIONS = [
        'categories' => true,
        'brands' => true,
        'featured' => true,
        'why' => true,
        'steps' => true,
        'types' => true,
        'whatsapp' => true,
        'cta' => true,
    ];

    /** Keys that carry a `:number` placeholder the admin must preserve. */
    public const PLACEHOLDER_KEYS = ['trust_licence', 'why_5_body'];

    /** Keys using Laravel's `singular|plural` form. */
    public const PLURAL_KEYS = ['min_order'];

    /**
     * A single string, admin override first and shipped translation second.
     *
     * @param  array<string, mixed>  $replace
     */
    public static function text(string $key, array $replace = []): string
    {
        $override = self::override($key);

        if ($override === null) {
            return __("shop.{$key}", $replace);
        }

        return self::interpolate($override, $replace);
    }

    /**
     * A pluralised string (`one|many`), honouring an admin override.
     *
     * @param  array<string, mixed>  $replace
     */
    public static function choice(string $key, int $number, array $replace = []): string
    {
        $override = self::override($key);

        if ($override === null) {
            return trans_choice("shop.{$key}", $number, $replace);
        }

        $line = app('translator')->getSelector()->choose($override, $number, app()->getLocale());

        return self::interpolate($line, $replace);
    }

    /** The raw override for the active locale, or null when unset/blank. */
    public static function override(string $key): ?string
    {
        $content = Setting::current()->homepage_content ?? [];
        $value = data_get($content, app()->getLocale().'.'.$key);

        return filled($value) ? (string) $value : null;
    }

    /**
     * The optional sections in the order they should render.
     *
     * The stored list is treated as a preference, not a specification: keys that
     * no longer exist are dropped and sections missing from it are appended in
     * their shipped order. That way adding a seventh section in a later release
     * cannot make it invisible on an install that saved an order of six.
     *
     * @return array<int, string>
     */
    public static function sectionOrder(): array
    {
        $stored = Setting::current()->homepage_section_order ?? [];
        $known = array_keys(self::SECTIONS);

        $ordered = array_values(array_intersect(
            array_filter((array) $stored, 'is_string'),
            $known,
        ));

        return array_values(array_unique(array_merge($ordered, $known)));
    }

    public static function showsSection(string $section): bool
    {
        $stored = Setting::current()->homepage_sections ?? [];

        // A section absent from the stored map has never been touched, so it
        // keeps its shipped default rather than silently disappearing.
        return (bool) ($stored[$section] ?? self::SECTIONS[$section] ?? true);
    }

    /** Admin-uploaded hero image URL, or null to fall back to the product mosaic. */
    public static function heroImage(): ?string
    {
        $path = Setting::current()->homepage_hero_image_path;

        return filled($path) ? Storage::disk('public')->url($path) : null;
    }

    /**
     * Hand-picked featured product ids, in the order chosen.
     *
     * Empty means "choose automatically" — the caller falls back rather than
     * rendering an empty grid.
     *
     * @return array<int, int>
     */
    public static function featuredProductIds(): array
    {
        $ids = Setting::current()->homepage_featured_product_ids ?? [];

        return array_values(array_unique(array_filter(
            array_map('intval', (array) $ids),
            fn (int $id) => $id > 0,
        )));
    }

    /**
     * Hand-picked brand names, in the order chosen. Empty means automatic.
     *
     * @return array<int, string>
     */
    public static function featuredBrandNames(): array
    {
        $names = Setting::current()->homepage_featured_brands ?? [];

        return array_values(array_unique(array_filter(
            array_map(fn ($name) => trim((string) $name), (array) $names),
            fn (string $name) => $name !== '',
        )));
    }

    public static function featuredCount(): int
    {
        $count = (int) (Setting::current()->homepage_featured_count ?: 8);

        // Clamped so a stray value cannot render one card or a thousand.
        return max(4, min($count, 24));
    }

    /** Every editable key, flattened. @return array<int, string> */
    public static function keys(): array
    {
        return array_merge(...array_map('array_keys', array_values(self::GROUPS)));
    }

    /** @param  array<string, mixed>  $replace */
    private static function interpolate(string $line, array $replace): string
    {
        foreach ($replace as $search => $value) {
            $line = str_replace(':'.$search, (string) $value, $line);
        }

        return $line;
    }
}
