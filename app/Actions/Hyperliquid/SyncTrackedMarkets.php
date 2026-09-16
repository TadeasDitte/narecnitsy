<?php

namespace App\Actions\Hyperliquid;

use App\Models\Market;
use App\Services\Hyperliquid\HyperliquidClient;

class SyncTrackedMarkets
{
    public function __construct(private HyperliquidClient $client) {}

    /**
     * Seed/refresh Market rows for every tracked symbol from Hyperliquid's meta endpoint.
     */
    public function __invoke(): void
    {
        $trackedSymbols = config('hyperliquid.symbols');
        $universe = $this->client->meta()['universe'] ?? [];

        foreach ($universe as $entry) {
            if (! in_array($entry['name'], $trackedSymbols, true)) {
                continue;
            }

            Market::updateOrCreate(
                ['symbol' => $entry['name']],
                [
                    'sz_decimals' => $entry['szDecimals'] ?? null,
                    'max_leverage' => $entry['maxLeverage'] ?? null,
                    'metadata' => $entry,
                ],
            );
        }
    }
}
