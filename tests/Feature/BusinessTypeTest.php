<?php

namespace Tests\Feature;

use App\Models\BusinessType;
use App\Support\BusinessTypeIcons;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin-managed "Business types we serve" chips.
 */
class BusinessTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_shipped_types_are_seeded_with_matching_icons(): void
    {
        $types = BusinessType::forStorefront();

        $this->assertCount(6, $types);
        $this->assertSame(
            6,
            $types->pluck('icon')->unique()->count(),
            'Each seeded type should have its own icon, not one repeated glyph.'
        );
    }

    public function test_the_homepage_renders_a_chip_and_icon_for_each_type(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        foreach (BusinessType::forStorefront() as $type) {
            $this->assertStringContainsString($type->label, $html);
            $this->assertStringContainsString($type->icon_path, $html);
        }
    }

    public function test_a_hidden_type_disappears_from_the_homepage(): void
    {
        $type = BusinessType::first();
        $type->update(['is_visible' => false]);

        $this->get(route('home'))->assertOk()->assertDontSee($type->name_en);
    }

    public function test_types_render_in_the_configured_order(): void
    {
        BusinessType::query()->delete();
        BusinessType::create(['name_en' => 'Zulu Traders', 'sort_order' => 1, 'is_visible' => true]);
        BusinessType::create(['name_en' => 'Alpha Traders', 'sort_order' => 0, 'is_visible' => true]);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertLessThan(
            mb_strpos($html, 'Zulu Traders'),
            mb_strpos($html, 'Alpha Traders'),
            'sort_order should drive the chip order, not insertion order.'
        );
    }

    public function test_an_unknown_or_missing_icon_falls_back_instead_of_rendering_an_empty_path(): void
    {
        $type = BusinessType::create(['name_en' => 'Hotels', 'icon' => 'not-a-real-icon', 'is_visible' => true]);

        $this->assertSame(BusinessTypeIcons::path(BusinessTypeIcons::DEFAULT), $type->icon_path);
        $this->assertNotSame('', $type->icon_path);

        $type->update(['icon' => null]);
        $this->assertNotSame('', $type->refresh()->icon_path);
    }

    public function test_arabic_falls_back_to_the_english_name_when_not_translated(): void
    {
        $type = BusinessType::create(['name_en' => 'Hotels', 'name_ar' => null, 'is_visible' => true]);

        app()->setLocale('ar');
        $this->assertSame('Hotels', $type->label);

        $type->update(['name_ar' => 'الفنادق']);
        $this->assertSame('الفنادق', $type->refresh()->label);
    }

    public function test_the_section_falls_back_to_shipped_copy_if_every_type_is_switched_off(): void
    {
        // Better a stock list than a heading with nothing under it.
        BusinessType::query()->update(['is_visible' => false]);

        $this->get(route('home'))->assertOk()->assertSee(__('shop.type_pharmacies'));
    }
}
