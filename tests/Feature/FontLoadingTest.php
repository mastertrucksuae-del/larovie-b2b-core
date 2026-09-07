<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The brand fonts were bundled by Vite but the layout never called @fonts, so
 * the @font-face rules were never emitted and the storefront silently rendered
 * in system fallbacks. These lock that in.
 *
 * The families are the designer type system: Cormorant Garamond over Manrope for
 * Latin, Alexandria over IBM Plex Sans Arabic for Arabic.
 */
class FontLoadingTest extends TestCase
{
    use RefreshDatabase;

    public function test_english_pages_emit_latin_font_faces(): void
    {
        $html = $this->get(route('catalogue.index'))->assertOk()->getContent();

        $this->assertStringContainsString('@font-face', $html);
        $this->assertStringContainsString('Manrope', $html);
        $this->assertStringContainsString('Cormorant Garamond', $html);
    }

    public function test_arabic_pages_emit_arabic_font_faces(): void
    {
        $html = $this->get(route('catalogue.index').'?hl=ar')->assertOk()->getContent();

        $this->assertStringContainsString('@font-face', $html);
        $this->assertStringContainsString('IBM Plex Sans Arabic', $html);
        $this->assertStringContainsString('Alexandria', $html);
    }

    /**
     * Cormorant Garamond carries no Arabic glyphs and Alexandria has no Latin
     * role here, so shipping either script's faces to the other locale would be
     * pure dead weight on the critical path.
     */
    public function test_each_locale_only_ships_its_own_faces(): void
    {
        $english = $this->get(route('catalogue.index'))->assertOk()->getContent();
        $arabic = $this->get(route('catalogue.index').'?hl=ar')->assertOk()->getContent();

        // Matches the exact shape the fonts plugin emits — `font-family: "X"` —
        // so this cannot pass by simply never matching anything.
        $this->assertStringContainsString('font-family: "Cormorant Garamond"', $english);
        $this->assertStringNotContainsString('font-family: "Alexandria"', $english);

        $this->assertStringContainsString('font-family: "Alexandria"', $arabic);
        $this->assertStringNotContainsString('font-family: "Cormorant Garamond"', $arabic);
    }

    public function test_no_third_party_font_cdn_is_used(): void
    {
        $html = $this->get(route('catalogue.index').'?hl=ar')->assertOk()->getContent();

        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
        $this->assertStringNotContainsString('fonts.gstatic.com', $html);
    }
}
