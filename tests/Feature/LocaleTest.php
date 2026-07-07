<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_switching_to_arabic_persists_and_sets_rtl(): void
    {
        $this->get('/locale/ar');
        $this->assertSame('ar', session('locale'));

        $this->get('/')
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('lang="ar"', false);
    }

    public function test_defaults_to_english_ltr(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('dir="ltr"', false)
            ->assertSee('lang="en"', false);
    }

    public function test_invalid_locale_is_ignored(): void
    {
        $this->get('/locale/fr');
        $this->assertNull(session('locale'));
    }
}
