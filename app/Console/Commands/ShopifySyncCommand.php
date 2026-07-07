<?php

namespace App\Console\Commands;

use App\Services\Shopify\ProductSyncService;
use App\Services\Shopify\ShopifyClient;
use Illuminate\Console\Command;

class ShopifySyncCommand extends Command
{
    protected $signature = 'shopify:sync';

    protected $description = 'Import products and variants from Shopify (preserves admin-owned fields)';

    public function handle(ShopifyClient $client, ProductSyncService $service): int
    {
        if (! $client->isConfigured()) {
            $this->error('Shopify is not configured. Set SHOPIFY_SHOP_DOMAIN and SHOPIFY_ADMIN_TOKEN in .env.');

            return self::FAILURE;
        }

        $this->info('Syncing from '.config('shopify.store_domain').' (API '.config('shopify.api_version').')…');

        try {
            $summary = $service->sync();
        } catch (\Throwable $e) {
            $this->error('Sync failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Created', 'Updated', 'Archived', 'Variants'],
            [[$summary['created'], $summary['updated'], $summary['archived'], $summary['variants']]],
        );
        $this->info('Done.');

        return self::SUCCESS;
    }
}
