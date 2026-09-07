<?php

namespace App\Filament\Pages;

use App\Filament\Support\WebpUpload;
use App\Models\Product;
use App\Models\Setting;
use App\Support\HomeContent;
use BackedEnum;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageSettings extends Page
{
    protected string $view = 'filament.pages.manage-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $title = 'Settings';

    protected static ?int $navigationSort = 9;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $data = Setting::current()->attributesToArray();

        // A never-touched `homepage_sections` is null, which Filament would
        // render as every toggle off — and the first save would then persist
        // that and blank the homepage. Seed the shipped defaults instead.
        $data['homepage_sections'] = array_merge(
            HomeContent::SECTIONS,
            is_array($data['homepage_sections'] ?? null) ? $data['homepage_sections'] : [],
        );

        // Same reasoning as the toggles: a null order column would render an
        // empty reorder list with nothing to drag.
        $data['homepage_section_order'] = HomeContent::sectionOrder();

        $this->form->fill($data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Branding')
                    ->columns(2)
                    ->schema([
                        TextInput::make('company_name')->required(),
                        TextInput::make('legal_entity_name')
                            ->label('Registered legal entity name')
                            ->helperText('Shown in the footer & Contact page for buyer credibility.'),
                        ColorPicker::make('brand_color'),
                        WebpUpload::make('logo_path', 'branding')
                            ->label('Logo')
                            ->columnSpanFull(),
                        TextInput::make('company_email')->email(),
                        TextInput::make('company_phone')->label('Phone (tap-to-call)'),
                        TextInput::make('company_whatsapp')
                            ->label('WhatsApp Business number')
                            ->helperText('Used for the wa.me tap-to-chat links. Falls back to phone if empty.'),
                        Textarea::make('company_address')->rows(2)->columnSpanFull(),
                        TextInput::make('trn')->label('Tax registration number (TRN)'),
                        TextInput::make('trade_licence_number')->label('Trade licence number'),
                    ]),

                Section::make('Contact & location')
                    ->columns(2)
                    ->schema([
                        TextInput::make('contact_hours')
                            ->label('Business hours')
                            ->placeholder('Sun–Thu, 9am–6pm GST')
                            ->columnSpanFull(),
                        Textarea::make('google_maps_embed')
                            ->label('Google Maps embed URL')
                            ->helperText('The map "src" URL from Google Maps → Share → Embed a map. Rendered on the Contact page.')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('Authenticity guarantee')
                    ->description('Founder-approved copy for the Authenticity page. State only what is verifiably true.')
                    ->schema([
                        Textarea::make('authenticity_statement_en')->label('English')->rows(4),
                        Textarea::make('authenticity_statement_ar')->label('Arabic')->rows(4),
                    ]),

                Section::make('Homepage')
                    ->description('Everything on the public homepage. Leave a field blank to use the built-in wording for that language — clearing a box restores the default rather than emptying the section.')
                    ->schema([
                        WebpUpload::make('homepage_hero_image_path', 'homepage')
                            ->label('Hero image')
                            ->helperText('Replaces the automatic four-product collage. Landscape, roughly 4:3. Converted to WebP and resized.')
                            ->columnSpanFull(),
                        TextInput::make('homepage_featured_count')
                            ->label('Featured products shown (automatic mode)')
                            ->helperText('Only used when no products are picked below.')
                            ->numeric()->minValue(4)->maxValue(24)->default(8),

                        Select::make('homepage_featured_product_ids')
                            ->label('Featured products')
                            ->helperText('Leave empty to show the earliest products that have images. Picked products appear in the order you add them.')
                            ->multiple()
                            ->searchable()
                            // Searched rather than listing every option: the
                            // catalogue runs to hundreds of products and
                            // rendering them all would bloat the page.
                            ->getSearchResultsUsing(fn (string $search) => Product::query()
                                ->publiclyVisible()
                                ->where('title', 'like', '%'.$search.'%')
                                ->orderBy('title')
                                ->limit(50)
                                ->pluck('title', 'id')
                                ->all())
                            ->getOptionLabelsUsing(fn (array $values) => Product::query()
                                ->whereIn('id', $values)
                                ->pluck('title', 'id')
                                ->all())
                            ->columnSpanFull(),

                        Select::make('homepage_featured_brands')
                            ->label('Featured brands')
                            ->helperText('Leave empty to show the brands with the most products. A brand with nothing in stock is skipped.')
                            ->multiple()
                            ->searchable()
                            ->options(fn () => self::brandOptions())
                            ->columnSpanFull(),

                        Section::make('Sections shown')
                            ->description('The hero and trust strip always sit at the top of the page.')
                            ->columns(3)
                            ->schema(self::sectionToggles()),

                        Section::make('Section order')
                            ->description('Drag to change the order these sections appear down the homepage.')
                            ->schema([
                                Repeater::make('homepage_section_order')
                                    ->hiddenLabel()
                                    ->simple(
                                        Select::make('section')
                                            ->options(self::sectionLabels())
                                            ->required()
                                    )
                                    ->addable(false)
                                    ->deletable(false)
                                    ->reorderable()
                                    ->columnSpanFull(),
                            ]),

                        ...self::copyFields(),
                    ]),

                Section::make('Measurement & notifications')
                    ->columns(2)
                    ->schema([
                        TextInput::make('notification_email')
                            ->label('New-inquiry alert email')
                            ->email()
                            ->helperText('Instant email on every new inquiry. Falls back to the company email.'),
                        TextInput::make('ga4_measurement_id')
                            ->label('GA4 Measurement ID')
                            ->placeholder('G-XXXXXXXXXX')
                            ->helperText('The gtag.js snippet only loads when this is set.'),
                    ]),

                Section::make('Business accounts')
                    ->description('Controls what happens when a customer registers on the storefront.')
                    ->schema([
                        Toggle::make('require_account_review')
                            ->label('Require manual review before an account is approved')
                            ->helperText('ON: registrations arrive as "Pending review" and wait for you to approve them here. OFF: new accounts are approved the moment they register and can see wholesale pricing straight away. Either way, only approved accounts see prices.'),
                    ]),

                Section::make('Search indexing')
                    ->description('ON: the storefront is indexable, robots.txt allows crawling and /sitemap.xml is served. OFF: noindex/nofollow, robots.txt disallows everything and the sitemap 404s. Leave ON unless you need to pull the site out of search.')
                    ->schema([
                        Toggle::make('search_indexing_enabled')
                            ->label('Allow search engines to index the storefront'),
                    ]),

                Section::make('Quote configuration')
                    ->columns(2)
                    ->schema([
                        TextInput::make('default_currency')->default('AED')->maxLength(3),
                        TextInput::make('quote_validity_days')->numeric()->minValue(1)->default(14),
                        Textarea::make('quote_terms')->rows(3)->columnSpanFull(),
                        Textarea::make('quote_footer_note')->rows(2)->columnSpanFull(),
                    ]),

                Section::make('WhatsApp message templates')
                    ->description('Placeholders: {customer_name} {reference} {quote_number} {quote_link}')
                    ->schema([
                        Textarea::make('whatsapp_message_template_en')->label('English')->rows(3),
                        Textarea::make('whatsapp_message_template_ar')->label('Arabic')->rows(3),
                    ]),
            ]);
    }

    /**
     * Visibility toggles, one per optional homepage section.
     *
     * @return array<int, Toggle>
     */
    /**
     * Brand names that actually appear on the storefront.
     *
     * Read off the products rather than the brands table: a storefront "brand"
     * is `brand ?: vendor` resolved per product, so this is the only list whose
     * values match what the homepage section groups and links on.
     *
     * @return array<string, string>
     */
    private static function brandOptions(): array
    {
        return Product::query()
            ->publiclyVisible()
            ->get(['brand', 'vendor'])
            ->map(fn (Product $product) => (string) $product->effective_brand)
            ->filter()
            ->unique()
            ->sort()
            ->mapWithKeys(fn (string $name) => [$name => $name])
            ->all();
    }

    /** @return array<string, string> */
    private static function sectionLabels(): array
    {
        return [
            'categories' => 'Shop by category',
            'brands' => 'Featured brands',
            'featured' => 'Featured products',
            'why' => 'Why partner with us',
            'steps' => 'How ordering works',
            'types' => 'Business types served',
            'whatsapp' => 'WhatsApp band',
            'cta' => 'Account call-to-action',
        ];
    }

    private static function sectionToggles(): array
    {
        $labels = self::sectionLabels();

        return collect(HomeContent::SECTIONS)
            ->map(fn (bool $default, string $key) => Toggle::make("homepage_sections.{$key}")
                ->label($labels[$key] ?? $key)
                ->default($default))
            ->values()
            ->all();
    }

    /**
     * One collapsible group per block of homepage copy, English beside Arabic.
     *
     * Built from HomeContent::GROUPS so the form can never drift from what the
     * page actually renders: a new editable line is added in one place.
     *
     * @return array<int, Section>
     */
    private static function copyFields(): array
    {
        $sections = [];

        foreach (HomeContent::GROUPS as $group => $fields) {
            $components = [];

            foreach ($fields as $key => $type) {
                foreach (['en' => 'English', 'ar' => 'Arabic'] as $locale => $language) {
                    $component = $type === 'area'
                        ? Textarea::make("homepage_content.{$locale}.{$key}")->rows(2)
                        : TextInput::make("homepage_content.{$locale}.{$key}");

                    $components[] = $component
                        ->label(str($key)->replace('_', ' ')->ucfirst().' — '.$language)
                        // The shipped copy shows through as the placeholder, so
                        // an empty box reads as "this is what visitors see".
                        ->placeholder(__("shop.{$key}", [], $locale))
                        ->helperText(self::fieldHint($key));
                }
            }

            $sections[] = Section::make($group)
                ->columns(2)
                ->collapsed()
                ->schema($components);
        }

        return $sections;
    }

    private static function fieldHint(string $key): ?string
    {
        if (in_array($key, HomeContent::PLACEHOLDER_KEYS, true)) {
            return 'Keep :number where the trade licence number should appear.';
        }

        if (in_array($key, HomeContent::PLURAL_KEYS, true)) {
            return 'Two forms separated by | — singular first, e.g. ":count unit|:count units".';
        }

        return null;
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Blank boxes mean "use the shipped wording", so they are dropped rather
        // than stored as nulls. This keeps the column holding real overrides
        // only, and lets a cleared field genuinely fall back.
        if (isset($data['homepage_content']) && is_array($data['homepage_content'])) {
            $content = [];

            foreach ($data['homepage_content'] as $locale => $values) {
                $kept = array_filter((array) $values, fn ($value) => filled($value));

                if ($kept !== []) {
                    $content[$locale] = $kept;
                }
            }

            $data['homepage_content'] = $content ?: null;
        }

        $setting = Setting::current();
        $setting->update($data);
        Setting::clearCache();

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}
