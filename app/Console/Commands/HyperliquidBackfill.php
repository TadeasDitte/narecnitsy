<?php

namespace App\Console\Commands;

use App\Actions\Hyperliquid\QueueBackfillForAllMarkets;
use App\Actions\Hyperliquid\SyncTrackedMarkets;
use App\Jobs\BackfillCandlesJob;
use App\Models\Market;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;

class HyperliquidBackfill extends Command
{
    protected $signature = 'hyperliquid:backfill
        {symbol? : Single coin symbol, e.g. BTC}
        {interval? : Single interval, e.g. 1m}
        {--from= : Start of the backfill window, e.g. "-90 days" (default: config(hyperliquid.backfill.default_from), i.e. as much history as Hyperliquid has)}
        {--to= : End of the backfill window (default: now)}
        {--all : Backfill every configured symbol x interval combination}';

    protected $description = 'Backfill historical OHLCV candles from Hyperliquid into the candles table';

    public function handle(SyncTrackedMarkets $syncTrackedMarkets, QueueBackfillForAllMarkets $queueBackfillForAllMarkets): int
    {
        $symbol = $this->argument('symbol');
        $interval = $this->argument('interval');

        if ($symbol && $interval) {
            $syncTrackedMarkets();

            try {
                $market = Market::where('symbol', $symbol)->firstOrFail();
            } catch (ModelNotFoundException) {
                $this->error("Unknown or untracked symbol: {$symbol}");

                return self::FAILURE;
            }

            $fromMs = Carbon::parse($this->option('from') ?? config('hyperliquid.backfill.default_from'))->getTimestampMs();
            $toMs = Carbon::parse($this->option('to') ?? 'now')->getTimestampMs();

            BackfillCandlesJob::dispatch($market->id, $interval, $fromMs, $toMs);
            $this->info("Queued backfill: {$market->symbol} {$interval}");

            return self::SUCCESS;
        }

        if ($this->option('all')) {
            $fromMs = $this->option('from') ? Carbon::parse($this->option('from'))->getTimestampMs() : null;
            $toMs = $this->option('to') ? Carbon::parse($this->option('to'))->getTimestampMs() : null;

            $queued = $queueBackfillForAllMarkets($fromMs, $toMs);
            $this->info("Queued {$queued} symbol/interval backfills.");

            return self::SUCCESS;
        }

        $this->error('Nothing to backfill. Pass a symbol + interval, or use --all.');

        return self::FAILURE;
    }
}
