<?php

namespace App\Services\Shopify;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin GraphQL Admin API client with cost-based throttle handling and
 * automatic token refresh for managed-install apps (client_credentials grant).
 */
class ShopifyClient
{
    protected const TOKEN_CACHE_KEY = 'shopify.access_token';

    public function __construct(
        protected ?string $domain = null,
        protected ?string $token = null,
        protected ?string $version = null,
        protected ?string $apiKey = null,
        protected ?string $apiSecret = null,
    ) {
        $this->domain = $domain ?? config('shopify.store_domain');
        $this->token = $token ?? config('shopify.admin_api_token');
        $this->version = $version ?? config('shopify.api_version');
        $this->apiKey = $apiKey ?? config('shopify.api_key');
        $this->apiSecret = $apiSecret ?? config('shopify.api_secret');
    }

    public function isConfigured(): bool
    {
        // Usable if we have either a static token or the credentials to mint one.
        return filled($this->domain) && (filled($this->token) || $this->canRefresh());
    }

    protected function canRefresh(): bool
    {
        return filled($this->apiKey) && filled($this->apiSecret);
    }

    /**
     * The current access token: a cached client_credentials token when the app
     * can mint one, otherwise the static admin token from config.
     */
    public function accessToken(bool $forceRefresh = false): string
    {
        if ($this->canRefresh()) {
            if ($forceRefresh) {
                Cache::forget(self::TOKEN_CACHE_KEY);
            }

            return Cache::remember(
                self::TOKEN_CACHE_KEY,
                now()->addHours(20),
                fn () => $this->requestNewToken(),
            );
        }

        return (string) $this->token;
    }

    /** Exchange API key/secret for a fresh admin token (client_credentials grant). */
    protected function requestNewToken(): string
    {
        $domain = rtrim((string) $this->domain, '/');

        $response = Http::asJson()->timeout(20)->post("https://{$domain}/admin/oauth/access_token", [
            'client_id' => $this->apiKey,
            'client_secret' => $this->apiSecret,
            'grant_type' => 'client_credentials',
        ]);

        if ($response->failed() || blank($response->json('access_token'))) {
            throw new RuntimeException('Shopify token refresh failed: HTTP '.$response->status().' '.$response->body());
        }

        return (string) $response->json('access_token');
    }

    protected function endpoint(): string
    {
        $domain = rtrim((string) $this->domain, '/');

        return "https://{$domain}/admin/api/{$this->version}/graphql.json";
    }

    protected function request(string $token): PendingRequest
    {
        return Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
            'Content-Type' => 'application/json',
        ])->timeout(30)->retry(2, 1000, throw: false);
    }

    /**
     * Execute a GraphQL query. Returns the `data` payload.
     * Refreshes the token once on 401, and honors cost-based rate limiting.
     *
     * @throws RuntimeException on transport or GraphQL errors.
     */
    public function query(string $query, array $variables = []): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Shopify is not configured. Set SHOPIFY_SHOP_DOMAIN and SHOPIFY_ADMIN_TOKEN (or API key/secret).');
        }

        $response = $this->request($this->accessToken())->post($this->endpoint(), [
            'query' => $query,
            'variables' => (object) $variables,
        ]);

        // Expired/invalid token — mint a fresh one and retry once.
        if ($response->status() === 401 && $this->canRefresh()) {
            $response = $this->request($this->accessToken(forceRefresh: true))->post($this->endpoint(), [
                'query' => $query,
                'variables' => (object) $variables,
            ]);
        }

        if ($response->status() === 429) {
            $this->sleep(2_000);
            $response = $this->request($this->accessToken())->post($this->endpoint(), [
                'query' => $query,
                'variables' => (object) $variables,
            ]);
        }

        if ($response->failed()) {
            throw new RuntimeException("Shopify API HTTP {$response->status()}: ".$response->body());
        }

        $json = $response->json();

        if (! empty($json['errors'])) {
            $message = collect($json['errors'])->pluck('message')->filter()->implode('; ');
            throw new RuntimeException('Shopify GraphQL error: '.($message ?: json_encode($json['errors'])));
        }

        $this->respectThrottle($json['extensions']['cost'] ?? null);

        return $json['data'] ?? [];
    }

    /**
     * If the remaining query-cost bucket is low, sleep long enough for it to refill.
     */
    protected function respectThrottle(?array $cost): void
    {
        $throttle = $cost['throttleStatus'] ?? null;
        if (! $throttle) {
            return;
        }

        $available = (float) ($throttle['currentlyAvailable'] ?? 1000);
        $restoreRate = (float) ($throttle['restoreRate'] ?? 50);
        $floor = (float) config('shopify.cost_floor', 200);

        if ($available < $floor && $restoreRate > 0) {
            $deficit = $floor - $available;
            $seconds = min(4.0, $deficit / $restoreRate);
            $this->sleep((int) ($seconds * 1000));
        }
    }

    protected function sleep(int $milliseconds): void
    {
        usleep($milliseconds * 1000);
    }
}
