<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The brand fonts were bundled by Vite but the layout never called @fonts, so
 * the @font-face rules were never emitted and the storefront silently rendered
 * in system fallbacks. These lock that in.
 */
class FontLoadingTest extends TestCase
{
    use RefreshDatabase;

    public function test_english_pages_emit_latin_font_faces(): void
    {
        $html = $this->get(route('catalogue.index'))->assertOk()->getContent();

        $this->assertStringContainsString('@font-face', $html);
        $this->assertStringContainsString('Inter', $html);
        $this->assertStringContainsString('Playfair Display', $html);
    }

    public function test_arabic_pages_emit_cairo_font_faces(): void
    {
        $html = $this->get(route('catalogue.index').'?hl=ar')->assertOk()->getContent();

        $this->assertStringContainsString('@font-face', $html);
        $this->assertStringContainsString('Cairo', $html);
    }

    public function test_no_third_party_font_cdn_is_used(): void
    {
        $html = $this->get(route('catalogue.index').'?hl=ar')->assertOk()->getContent();

        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
        $this->assertStringNotContainsString('fonts.gstatic.com', $html);
    }
}
