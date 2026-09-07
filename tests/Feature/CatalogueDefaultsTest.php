<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSettings;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Catalogue and sync knobs moved out of .env and into the dashboard.
 *
 * Config remains the floor, so `.env` still works as the shipped default and a
 * cleared field cannot take the sync down.
 */
class CatalogueDefaultsTest extends TestCase
{
    use RefreshDatabase;

    private function settings(array $values = []): Setting
    {
        $setting = Setting::current();

        if ($values !== []) {
            $setting->update($values);
            Setting::clearCache();
        }

        return Setting::current();
    }

    public function test_unset_values_fall_back_to_config(): void
    {
        config([
            'shopify.default_moq' => 12,
            'shopify.page_size' => 50,
            'shopify.cost_floor' => 200,
        ]);

        $setting = $this->settings(['default_moq' => null, 'sync_page_size' => null, 'sync_cost_floor' => null]);

        $this->assertSame(12, $setting->effectiveDefaultMoq());
        $this->assertSame(50, $setting->effectiveSyncPageSize());
        $this->assertSame(200, $setting->effectiveSyncCostFloor());
    }

    public function test_a_dashboard_value_overrides_config(): void
    {
        config(['shopify.default_moq' => 12]);

        $this->assertSame(24, $this->settings(['default_moq' => 24])->effectiveDefaultMoq());
    }

    public function test_clearing_a_value_returns_to_config(): void
    {
        config(['shopify.default_moq' => 12]);

        $this->settings(['default_moq' => 24]);

        $this->assertSame(12, $this->settings(['default_moq' => null])->effectiveDefaultMoq());
    }

    public function test_values_are_clamped_to_what_the_api_accepts(): void
    {
        // Shopify rejects a page size above 250; a fat-fingered 9999 in the
        // dashboard should not break every sync until someone notices.
        $this->assertSame(250, $this->settings(['sync_page_size' => 9999])->effectiveSyncPageSize());
        $this->assertSame(1, $this->settings(['default_moq' => 0])->effectiveDefaultMoq() > 0 ? 1 : 0);
    }

    public function test_a_zero_is_treated_as_unset_rather_than_as_zero(): void
    {
        config(['shopify.default_moq' => 12]);

        // A MOQ of zero would let a buyer order nothing at all.
        $this->assertSame(12, $this->settings(['default_moq' => 0])->effectiveDefaultMoq());
    }

    public function test_the_settings_page_exposes_the_knobs(): void
    {
        $html = $this->actingAs(User::factory()->create(), 'web')
            ->get(ManageSettings::getUrl())
            ->assertOk()
            ->getContent();

        foreach (['default_moq', 'sync_page_size', 'sync_cost_floor'] as $field) {
            $this->assertStringContainsString($field, $html);
        }
    }

    public function test_credentials_are_not_exposed_in_the_admin(): void
    {
        // Secrets stay in .env deliberately: a database row the panel renders
        // widens their blast radius from "can read the server" to "can reach an
        // admin session".
        $html = $this->actingAs(User::factory()->create(), 'web')
            ->get(ManageSettings::getUrl())
            ->assertOk()
            ->getContent();

        foreach (['admin_api_token', 'api_secret', 'webhook_secret', 'SHOPIFY_ADMIN_TOKEN'] as $secret) {
            $this->assertStringNotContainsString($secret, $html);
        }
    }
}
