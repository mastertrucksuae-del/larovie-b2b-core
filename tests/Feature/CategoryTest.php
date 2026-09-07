<?php

namespace Tests\Feature;

use App\Filament\Resources\Categories\CategoryResource;
use App\Models\Category;
use App\Models\Setting;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Shopify\ProductSyncService;
use App\Services\Shopify\ShopifyClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Categories imported from Shopify collections.
 *
 * Replaces the keyword-guessed fallback in App\Support\DerivedCategories, which
 * stays only for stores that have not imported yet.
 */
class CategoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Import the given collections.
     *
     * @param  array<int, array{id:int, title:string, handle?:string, image?:?string, products?:array<int,int>}>  $collections
     */
    private function import(array $collections): void
    {
        $client = new class($collections) extends ShopifyClient
        {
            public function __construct(public array $collections) {}

            public function isConfigured(): bool
            {
                return true;
            }

            public function query(string $query, array $variables = []): array
            {
                if (! str_contains($query, 'collections(')) {
                    // Products and brand metaobjects are covered elsewhere.
                    return ['products' => ['pageInfo' => ['hasNextPage' => false], 'nodes' => []]];
                }

                return ['collections' => [
                    'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                    'nodes' => array_map(fn (array $c) => [
                        'legacyResourceId' => $c['id'],
                        'handle' => $c['handle'] ?? 'c-'.$c['id'],
                        'title' => $c['title'],
                        'image' => isset($c['image']) ? ['url' => $c['image']] : null,
                        'products' => ['nodes' => array_map(
                            fn (int $pid) => ['legacyResourceId' => $pid],
                            $c['products'] ?? []
                        )],
                    ], $this->collections),
                ]];
            }
        };

        (new ProductSyncService($client))->syncCollections();
    }

    private function product(int $shopifyId, string $title = 'Heartleaf Toner'): Product
    {
        $product = Product::factory()->create([
            'shopify_product_id' => $shopifyId,
            'title' => $title,
            'is_visible' => true,
            'is_archived' => false,
            'is_bundle' => false,
            'featured_image_url' => 'https://cdn.shopify.com/x.jpg',
        ]);

        ProductVariant::factory()->for($product)->create([
            'is_visible' => true,
            'is_archived' => false,
        ]);

        return $product;
    }

    // ── Importing ───────────────────────────────────────────────────────────

    public function test_collections_import_as_categories_with_their_products(): void
    {
        $this->product(1);
        $this->product(2, 'Rice Toner');

        $this->import([
            ['id' => 100, 'title' => 'Toners', 'image' => 'https://cdn.shopify.com/t.jpg', 'products' => [1, 2]],
        ]);

        $category = Category::firstOrFail();

        $this->assertSame('Toners', $category->title);
        $this->assertSame(100, (int) $category->shopify_collection_id);
        $this->assertSame(2, $category->products()->count());
        $this->assertNotNull($category->synced_at);
    }

    public function test_imported_categories_start_hidden(): void
    {
        // An import must never silently publish a new section of the storefront.
        $this->import([['id' => 100, 'title' => 'Toners']]);

        $this->assertFalse(Category::firstOrFail()->is_visible);
    }

    public function test_reimport_refreshes_shopify_fields_but_keeps_admin_ones(): void
    {
        $this->import([['id' => 100, 'title' => 'Toners']]);

        Category::firstOrFail()->update([
            'is_visible' => true,
            'title_ar' => 'تونر',
            'image_path' => 'category-images/mine.webp',
            'sort_order' => 3,
        ]);

        $this->import([['id' => 100, 'title' => 'Toners & Mists', 'image' => 'https://cdn.shopify.com/new.jpg']]);

        $category = Category::firstOrFail();

        $this->assertSame('Toners & Mists', $category->title, 'Shopify owns the title.');
        $this->assertSame('https://cdn.shopify.com/new.jpg', $category->image_url);

        $this->assertTrue($category->is_visible, 'Visibility is the admin\'s.');
        $this->assertSame('تونر', $category->title_ar);
        $this->assertSame('category-images/mine.webp', $category->image_path);
        $this->assertSame(3, $category->sort_order);
    }

    public function test_a_collection_removed_upstream_is_archived_not_deleted(): void
    {
        $this->import([
            ['id' => 100, 'title' => 'Toners'],
            ['id' => 200, 'title' => 'Cleansers'],
        ]);

        $this->import([['id' => 100, 'title' => 'Toners']]);

        $this->assertSame(2, Category::count(), 'Settings and translations must survive.');
        $this->assertTrue(Category::where('shopify_collection_id', 200)->firstOrFail()->is_archived);
        $this->assertFalse(Category::where('shopify_collection_id', 100)->firstOrFail()->is_archived);
    }

    // ── The storefront ──────────────────────────────────────────────────────

    public function test_the_homepage_uses_imported_categories_once_any_are_visible(): void
    {
        $this->product(1);
        $this->import([['id' => 100, 'title' => 'Distinctive Toners', 'products' => [1]]]);
        Category::firstOrFail()->update(['is_visible' => true]);

        $this->get(route('home'))->assertOk()->assertSee('Distinctive Toners');
    }

    public function test_the_homepage_falls_back_to_derived_categories_before_any_import(): void
    {
        $this->product(1, 'Heartleaf Soothing Toner');

        // Nothing imported: the keyword fallback keeps the section working.
        $this->get(route('home'))->assertOk()->assertSee(__('shop.cat_toners'));
    }

    public function test_hidden_archived_and_empty_categories_never_tile(): void
    {
        $this->product(1);

        $this->import([
            ['id' => 100, 'title' => 'Hidden Cat', 'products' => [1]],
            ['id' => 200, 'title' => 'Archived Cat', 'products' => [1]],
            ['id' => 300, 'title' => 'Empty Cat'],
            ['id' => 400, 'title' => 'Shown Cat', 'products' => [1]],
        ]);

        Category::where('shopify_collection_id', 200)->update(['is_visible' => true, 'is_archived' => true]);
        Category::where('shopify_collection_id', 300)->update(['is_visible' => true]);
        Category::where('shopify_collection_id', 400)->update(['is_visible' => true]);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('Shown Cat', $html);
        $this->assertStringNotContainsString('Hidden Cat', $html);
        $this->assertStringNotContainsString('Archived Cat', $html);
        $this->assertStringNotContainsString('Empty Cat', $html, 'A category with nothing in it would tile to an empty listing.');
    }

    public function test_a_category_tile_filters_the_catalogue(): void
    {
        $inside = $this->product(1, 'Inside The Category');
        $this->product(2, 'Outside The Category');

        $this->import([['id' => 100, 'title' => 'Toners', 'products' => [1]]]);
        $category = Category::firstOrFail();
        $category->update(['is_visible' => true]);

        $this->assertSame([$inside->id], $category->products()->pluck('products.id')->all());

        $this->get(route('catalogue.index', ['category' => $category->id]))->assertOk();
    }

    public function test_arabic_falls_back_to_the_english_name_until_translated(): void
    {
        $this->import([['id' => 100, 'title' => 'Toners']]);
        $category = Category::firstOrFail();

        app()->setLocale('ar');
        $this->assertSame('Toners', $category->label);

        $category->update(['title_ar' => 'تونر']);
        $this->assertSame('تونر', $category->refresh()->label);
    }

    // ── Hand-picking which categories tile ──────────────────────────────────

    private function importThreeVisible(): array
    {
        $this->product(1);

        $this->import([
            ['id' => 100, 'title' => 'Alpha Cat', 'products' => [1]],
            ['id' => 200, 'title' => 'Beta Cat', 'products' => [1]],
            ['id' => 300, 'title' => 'Gamma Cat', 'products' => [1]],
        ]);

        Category::query()->update(['is_visible' => true]);

        return Category::orderBy('shopify_collection_id')->pluck('id', 'title')->all();
    }

    public function test_all_visible_categories_tile_when_none_are_picked(): void
    {
        $this->importThreeVisible();

        $html = $this->get(route('home'))->assertOk()->getContent();

        foreach (['Alpha Cat', 'Beta Cat', 'Gamma Cat'] as $title) {
            $this->assertStringContainsString($title, $html);
        }
    }

    public function test_picking_categories_shows_exactly_those_in_the_chosen_order(): void
    {
        $ids = $this->importThreeVisible();

        Setting::current()->update([
            'homepage_featured_category_ids' => [$ids['Gamma Cat'], $ids['Alpha Cat']],
        ]);
        Setting::clearCache();

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Beta Cat', $html, 'An unpicked category must not tile.');
        $this->assertLessThan(
            mb_strpos($html, 'Alpha Cat'),
            mb_strpos($html, 'Gamma Cat'),
            'Categories should tile in the order they were picked.'
        );
    }

    public function test_a_picked_category_later_hidden_drops_off(): void
    {
        $ids = $this->importThreeVisible();

        Setting::current()->update([
            'homepage_featured_category_ids' => [$ids['Alpha Cat'], $ids['Beta Cat']],
        ]);
        Setting::clearCache();
        Category::where('id', $ids['Beta Cat'])->update(['is_visible' => false]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Alpha Cat')
            ->assertDontSee('Beta Cat');
    }

    public function test_clearing_the_picks_returns_to_automatic(): void
    {
        $this->importThreeVisible();

        Setting::current()->update(['homepage_featured_category_ids' => []]);
        Setting::clearCache();

        $this->get(route('home'))->assertOk()->assertSee('Beta Cat');
    }

    public function test_the_settings_page_offers_a_category_picker(): void
    {
        $this->importThreeVisible();

        $this->actingAs(User::factory()->create(), 'web')
            ->get(\App\Filament\Pages\ManageSettings::getUrl())
            ->assertOk()
            ->assertSee('homepage_featured_category_ids', escape: false);
    }

    // ── What actually tiles, and why ────────────────────────────────────────

    public function test_reordering_changes_the_homepage_order(): void
    {
        $this->product(1);
        $this->product(2);

        $this->import([
            ['id' => 100, 'title' => 'Alpha', 'products' => [1]],
            ['id' => 200, 'title' => 'Promotions', 'products' => [2]],
        ]);
        Category::query()->update(['is_visible' => true]);

        Category::where('title', 'Alpha')->update(['sort_order' => 1]);
        Category::where('title', 'Promotions')->update(['sort_order' => 0]);

        $this->assertSame(
            ['Promotions', 'Alpha'],
            Category::forHomepage(8)->pluck('title')->all()
        );
    }

    public function test_only_the_first_n_categories_tile(): void
    {
        // The silent rule that is easiest to trip over: reorder a category below
        // the cut-off and it vanishes even though it is switched on.
        $this->product(1);

        $collections = [];
        foreach (range(1, 10) as $i) {
            $collections[] = ['id' => 100 + $i, 'title' => "Cat {$i}", 'products' => [1]];
        }
        $this->import($collections);
        Category::query()->update(['is_visible' => true]);
        Category::query()->orderBy('id')->get()->each(fn ($c, $i) => $c->update(['sort_order' => $i]));

        $tiled = Category::forHomepage(8);

        $this->assertCount(8, $tiled);
        $this->assertFalse($tiled->contains('title', 'Cat 10'));
    }

    public function test_a_category_whose_products_are_all_hidden_does_not_tile(): void
    {
        $product = $this->product(1);
        $this->import([['id' => 100, 'title' => 'Promotions', 'products' => [1]]]);
        Category::query()->update(['is_visible' => true]);

        $this->assertCount(1, Category::forHomepage(8));

        $product->update(['is_visible' => false]);

        $this->assertCount(0, Category::forHomepage(8), 'It would otherwise tile through to an empty listing.');
    }

    public function test_picking_categories_overrides_the_reordering(): void
    {
        // The trap worth knowing: once anything is picked in Settings, dragging
        // rows on the Categories page no longer changes the homepage.
        $this->product(1);
        $this->import([
            ['id' => 100, 'title' => 'Alpha', 'products' => [1]],
            ['id' => 200, 'title' => 'Promotions', 'products' => [1]],
        ]);
        Category::query()->update(['is_visible' => true]);

        Setting::current()->update([
            'homepage_featured_category_ids' => [Category::where('title', 'Alpha')->value('id')],
        ]);
        Setting::clearCache();

        $tiled = Category::forHomepage(8)->pluck('title')->all();

        $this->assertSame(['Alpha'], $tiled);
        $this->assertNotContains('Promotions', $tiled);
    }

    // ── Artwork ─────────────────────────────────────────────────────────────

    public function test_a_category_without_its_own_image_borrows_one_from_a_product(): void
    {
        // Plenty of Shopify collections carry no image. Without this fallback
        // both the homepage tile and the admin list render an empty grey box.
        $this->product(1);
        $this->import([['id' => 100, 'title' => 'Toners', 'products' => [1]]]);

        $category = Category::firstOrFail();

        $this->assertNull($category->image_url);
        $this->assertSame('https://cdn.shopify.com/x.jpg', $category->display_image);
    }

    public function test_the_collection_image_wins_over_a_product_image(): void
    {
        $this->product(1);
        $this->import([['id' => 100, 'title' => 'Toners', 'image' => 'https://cdn.shopify.com/collection.jpg', 'products' => [1]]]);

        $this->assertSame('https://cdn.shopify.com/collection.jpg', Category::firstOrFail()->display_image);
    }

    public function test_an_admin_override_wins_over_everything(): void
    {
        $this->product(1);
        $this->import([['id' => 100, 'title' => 'Toners', 'image' => 'https://cdn.shopify.com/collection.jpg', 'products' => [1]]]);

        $category = Category::firstOrFail();
        $category->update(['image_path' => 'category-images/mine.webp']);

        $this->assertStringContainsString('category-images/mine.webp', $category->refresh()->display_image);
    }

    public function test_a_category_with_no_artwork_anywhere_returns_null(): void
    {
        $this->import([['id' => 100, 'title' => 'Empty']]);

        $this->assertNull(Category::firstOrFail()->display_image);
    }

    // ── The admin screen ────────────────────────────────────────────────────

    public function test_the_admin_can_manage_categories(): void
    {
        $this->import([['id' => 100, 'title' => 'Toners']]);
        $admin = User::factory()->create();

        $this->actingAs($admin, 'web')
            ->get(CategoryResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Toners');

        $this->actingAs($admin, 'web')
            ->get(CategoryResource::getUrl('edit', ['record' => Category::firstOrFail()]))
            ->assertOk();
    }

    public function test_categories_cannot_be_created_by_hand(): void
    {
        // They mirror Shopify collections; a hand-made row would have no
        // collection behind it to draw products from.
        $this->assertFalse(CategoryResource::canCreate());
    }
}
