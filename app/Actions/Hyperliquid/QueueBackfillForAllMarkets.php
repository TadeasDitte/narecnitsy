<?php

namespace App\Actions\Hyperliquid;

use App\Jobs\BackfillCandlesJob;
use App\Models\Market;
use Illuminate\Support\Carbon;

class QueueBackfillForAllMarkets
{
    public function __construct(private SyncTrackedMarkets $syncTrackedMarkets) {}

    /**
     * Syncs tracked markets from Hyperliquid, then queues a backfill for
     * every symbol x interval combination. Returns how many were queued.
     */
    public function __invoke(?int $fromMs = null, ?int $toMs = null): int
    {
        ($this->syncTrackedMarkets)();

        $fromMs ??= Carbon::parse(config('hyperliquid.backfill.default_from'))->getTimestampMs();
        $toMs ??= now()->getTimestampMs();

        $markets = Market::active()->whereIn('symbol', config('hyperliquid.symbols'))->get();
        $intervals = config('hyperliquid.intervals');

        $queued = 0;

        foreach ($markets as $market) {
            foreach ($intervals as $interval) {
                BackfillCandlesJob::dispatch($market->id, $interval, $fromMs, $toMs);
                $queued++;
            }
        }

        return $queued;
    }
}
