<?php

return [
    // Custom / managed-install app Admin API credentials (see .env).
    // Primary env keys match the names supplied by the Shopify app install;
    // legacy fallbacks are kept for convenience.
    'store_domain' => env('SHOPIFY_SHOP_DOMAIN', env('SHOPIFY_STORE_DOMAIN')),
    'admin_api_token' => env('SHOPIFY_ADMIN_TOKEN', env('SHOPIFY_ADMIN_API_TOKEN')),
    'api_version' => env('SHOPIFY_API_VERSION', '2025-10'),

    // Used to refresh the short-lived admin token via the client_credentials grant.
    'api_key' => env('SHOPIFY_API_KEY'),
    'api_secret' => env('SHOPIFY_API_SECRET'),
    'webhook_secret' => env('SHOPIFY_WEBHOOK_SECRET'),

    // Queue the sync job is dispatched onto (falls back to the default queue).
    'queue' => env('SHOPIFY_QUEUE', 'default'),

    // How many products to request per GraphQL page.
    'page_size' => (int) env('SHOPIFY_PAGE_SIZE', 50),

    // Back off when the GraphQL cost bucket drops below this many points.
    'cost_floor' => (int) env('SHOPIFY_COST_FLOOR', 200),
];
