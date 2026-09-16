<?php

namespace App\Jobs;

use App\Models\Candle;
use App\Models\Market;
use App\Services\Hyperliquid\HyperliquidClient;
use App\Services\Hyperliquid\HyperliquidRateLimitException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use InvalidArgumentException;

class BackfillCandlesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const MAX_CANDLES_PER_CALL = 5000;

    private const INTERVAL_MS = [
        '1m' => 60_000,
        '3m' => 180_000,
        '5m' => 300_000,
        '15m' => 900_000,
        '30m' => 1_800_000,
        '1h' => 3_600_000,
        '2h' => 7_200_000,
        '4h' => 14_400_000,
        '8h' => 28_800_000,
        '12h' => 43_200_000,
        '1d' => 86_400_000,
        '3d' => 259_200_000,
        '1w' => 604_800_000,
        '1M' => 2_592_000_000,
    ];

    public int $tries = 5;

    public function __construct(
        public int $marketId,
        public string $interval,
        public int $fromMs,
        public int $toMs,
    ) {}

    /**
     * Fetches and upserts exactly one 5000-candle window, then - if the window
     * came back full and there's still room before $fromMs - dispatches a new
     * job for the next (earlier) window. Chaining one window per job, rather
     * than looping over all windows inside a single job, keeps each job short
     * (one HTTP call) so backfilling years of 1m history can't run into a
     * queue worker's job timeout, and lets the shared rate-limit budget in
     * HyperliquidClient throttle the whole backfill regardless of how many
     * symbol/interval chains are running concurrently.
     */
    public function handle(HyperliquidClient $client): void
    {
        $market = Market::findOrFail($this->marketId);

        $intervalMs = self::INTERVAL_MS[$this->interval]
            ?? throw new InvalidArgumentException("Unknown interval: {$this->interval}");

        $windowMs = self::MAX_CANDLES_PER_CALL * $intervalMs;
        $windowStart = max($this->fromMs, $this->toMs - $windowMs);

        try {
            $candles = $client->candleSnapshot($market->symbol, $this->interval, $windowStart, $this->toMs);
        } catch (HyperliquidRateLimitException $e) {
            $this->release($e->retryAfterSeconds);

            return;
        }

        if (empty($candles)) {
            return;
        }

        $this->upsertCandles($market->id, $candles);

        // Hyperliquid's boundaries are inclusive, so an exactly-full window can
        // come back with one extra candle - check ">=", not "===".
        $windowWasFull = count($candles) >= self::MAX_CANDLES_PER_CALL;
        $moreHistoryPossible = $windowStart > $this->fromMs;

        if ($windowWasFull && $moreHistoryPossible) {
            self::dispatch($this->marketId, $this->interval, $this->fromMs, $windowStart);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $candles
     */
    private function upsertCandles(int $marketId, array $candles): void
    {
        $now = now();
        $nowMs = $now->getTimestampMs();

        $rows = array_map(fn (array $c) => [
            'market_id' => $marketId,
            'interval' => $c['i'],
            'open_time' => $c['t'],
            'close_time' => $c['T'],
            'open' => $c['o'],
            'high' => $c['h'],
            'low' => $c['l'],
            'close' => $c['c'],
            'volume' => $c['v'],
            'num_trades' => $c['n'] ?? null,
            'is_closed' => $c['T'] <= $nowMs,
            'created_at' => $now,
            'updated_at' => $now,
        ], $candles);

        Candle::upsert(
            $rows,
            uniqueBy: ['market_id', 'interval', 'open_time'],
            update: ['close_time', 'open', 'high', 'low', 'close', 'volume', 'num_trades', 'is_closed', 'updated_at'],
        );
    }
}
