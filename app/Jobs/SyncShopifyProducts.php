<?php

namespace App\Jobs;

use App\Services\Shopify\ProductSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncShopifyProducts implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;
    public int $tries = 1;

    /** Cache key used by the admin UI to reflect "sync running" state. */
    public const RUNNING_KEY = 'shopify.sync.running';
    public const RESULT_KEY = 'shopify.sync.last_result';

    public function __construct()
    {
        // Route to the dedicated Shopify queue (see SHOPIFY_QUEUE / config/shopify.php).
        $this->onQueue(config('shopify.queue', 'default'));
    }

    public function uniqueId(): string
    {
        return 'shopify-sync';
    }

    public function handle(ProductSyncService $service): void
    {
        Cache::put(self::RUNNING_KEY, true, now()->addMinutes(15));

        try {
            $summary = $service->sync();

            Cache::put(self::RESULT_KEY, [
                'ok' => true,
                'summary' => $summary,
                'at' => now()->toDateTimeString(),
            ], now()->addDay());

            Log::info('Shopify sync complete', $summary);
        } catch (Throwable $e) {
            Cache::put(self::RESULT_KEY, [
                'ok' => false,
                'error' => $e->getMessage(),
                'at' => now()->toDateTimeString(),
            ], now()->addDay());

            Log::error('Shopify sync failed: '.$e->getMessage());

            throw $e;
        } finally {
            Cache::forget(self::RUNNING_KEY);
        }
    }
}
