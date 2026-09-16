<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Network
    |--------------------------------------------------------------------------
    |
    | Which Hyperliquid environment to talk to: "testnet" or "mainnet".
    |
    */

    'network' => env('HYPERLIQUID_NETWORK', 'testnet'),

    'base_urls' => [
        'mainnet' => 'https://api.hyperliquid.xyz',
        'testnet' => 'https://api.hyperliquid-testnet.xyz',
    ],

    'ws_urls' => [
        'mainnet' => 'wss://api.hyperliquid.xyz/ws',
        'testnet' => 'wss://api.hyperliquid-testnet.xyz/ws',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tracked markets
    |--------------------------------------------------------------------------
    |
    | Curated list of coins to backfill/stream. Kept short on purpose - this
    | is a training-ground app, not a full mirror of Hyperliquid's ~100+
    | coin universe.
    |
    */

    'symbols' => array_filter(explode(',', (string) env('HYPERLIQUID_SYMBOLS', 'BTC,ETH,SOL,ARB,AVAX'))),

    'intervals' => ['1m', '5m', '15m', '1h', '4h', '1d'],

    /*
    |--------------------------------------------------------------------------
    | Backfill depth
    |--------------------------------------------------------------------------
    |
    | How far back `hyperliquid:backfill` walks by default when --from isn't
    | given. Set well before Hyperliquid's own launch so the backfill job's
    | own stopping condition (an empty/short page from candleSnapshot) is
    | always what ends the walk, not this bound - i.e. pull as much history
    | as the exchange actually has.
    |
    */

    'backfill' => [
        'default_from' => '2019-01-01',
    ],

    /*
    |--------------------------------------------------------------------------
    | REST rate limiting
    |--------------------------------------------------------------------------
    |
    | Hyperliquid budgets ~1200 weight/minute per IP across all /info calls.
    | We stay under that with a safety margin so bursts of queued jobs never
    | trip the real limit.
    |
    */

    'rate_limit' => [
        'weight_per_minute' => 1200,
        'safety_margin' => 0.85,
        'decay_seconds' => 60,
        'weights' => [
            'meta' => 20,
            'allMids' => 2,
            'l2Book' => 2,
            'candleSnapshot_base' => 20,
            'candleSnapshot_per_60_candles' => 1,
        ],
    ],

    'http' => [
        'timeout' => 10,
        'retry_times' => 3,
        'retry_sleep_ms' => 500,
    ],

];
