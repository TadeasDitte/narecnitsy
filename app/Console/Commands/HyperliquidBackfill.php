<?php

namespace App\Console\Commands;

use App\Jobs\BackfillCandlesJob;
use App\Models\Market;
use App\Services\Hyperliquid\HyperliquidClient;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class HyperliquidBackfill extends Command
{
    protected $signature = 'hyperliquid:backfill
        {symbol? : Single coin symbol, e.g. BTC}
        {interval? : Single interval, e.g. 1m}
        {--from= : Start of the backfill window, e.g. "-90 days" (default: config(hyperliquid.backfill.default_from), i.e. as much history as Hyperliquid has)}
        {--to= : End of the backfill window (default: now)}
        {--all : Backfill every configured symbol x interval combination}';

    protected $description = 'Backfill historical OHLCV candles from Hyperliquid into the candles table';

    public function handle(HyperliquidClient $client): int
    {
        $this->syncMarkets($client);

        try {
            $pairs = $this->resolvePairs();
        } catch (ModelNotFoundException) {
            $this->error("Unknown or untracked symbol: {$this->argument('symbol')}");

            return self::FAILURE;
        }

        if ($pairs->isEmpty()) {
            $this->error('Nothing to backfill. Pass a symbol + interval, or use --all.');

            return self::FAILURE;
        }

        $fromMs = Carbon::parse($this->option('from') ?? config('hyperliquid.backfill.default_from'))->getTimestampMs();
        $toMs = Carbon::parse($this->option('to') ?? 'now')->getTimestampMs();

        foreach ($pairs as [$market, $interval]) {
            BackfillCandlesJob::dispatch($market->id, $interval, $fromMs, $toMs);
            $this->info("Queued backfill: {$market->symbol} {$interval}");
        }

        return self::SUCCESS;
    }

    /**
     * Seed/refresh Market rows for every tracked symbol from Hyperliquid's meta endpoint.
     */
    private function syncMarkets(HyperliquidClient $client): void
    {
        $trackedSymbols = config('hyperliquid.symbols');
        $universe = $client->meta()['universe'] ?? [];

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

    /**
     * @return Collection<int, array{Market, string}>
     */
    private function resolvePairs(): Collection
    {
        $symbol = $this->argument('symbol');
        $interval = $this->argument('interval');

        if ($symbol && $interval) {
            $market = Market::where('symbol', $symbol)->firstOrFail();

            return collect([[$market, $interval]]);
        }

        if (! $this->option('all')) {
            return collect();
        }

        $markets = Market::active()->whereIn('symbol', config('hyperliquid.symbols'))->get();

        $pairs = collect();

        foreach ($markets as $market) {
            foreach (config('hyperliquid.intervals') as $trackedInterval) {
                $pairs->push([$market, $trackedInterval]);
            }
        }

        return $pairs;
    }
}
