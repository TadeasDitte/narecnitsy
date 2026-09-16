<?php

namespace App\Console\Commands;

use App\Jobs\BackfillCandlesJob;
use App\Jobs\UpsertCandleJob;
use App\Models\Market;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use WebSocket\Client;
use WebSocket\ConnectionException;

class HyperliquidStream extends Command
{
    protected $signature = 'hyperliquid:stream';

    protected $description = 'Maintain a live WebSocket connection to Hyperliquid and stream candles + mid prices';

    private const MAX_BACKOFF_SECONDS = 30;

    private const MID_PRICE_TTL_SECONDS = 30;

    private bool $shouldQuit = false;

    public function handle(): int
    {
        $markets = Market::active()->whereIn('symbol', config('hyperliquid.symbols'))->get()->keyBy('symbol');

        if ($markets->isEmpty()) {
            $this->error('No tracked markets found. Run `php artisan hyperliquid:backfill --all` first to seed markets.');

            return self::FAILURE;
        }

        $this->trapSignals();

        $wsUrl = config('hyperliquid.ws_urls.'.config('hyperliquid.network'));
        $backoffSeconds = 1;

        while (! $this->shouldQuit) {
            $connectedAt = now();

            try {
                $client = new Client($wsUrl, ['timeout' => 60]);
                $this->subscribeAll($client, $markets);
                $this->info("Connected to Hyperliquid stream: {$wsUrl}");
                $backoffSeconds = 1;

                while (! $this->shouldQuit) {
                    $this->handleMessage($client->receive(), $markets);
                }
            } catch (ConnectionException $e) {
                if ($this->shouldQuit) {
                    break;
                }

                $this->warn("Stream disconnected ({$e->getMessage()}), reconnecting in {$backoffSeconds}s...");
                $this->patchGap($markets, $connectedAt);
                sleep($backoffSeconds);
                $backoffSeconds = min($backoffSeconds * 2, self::MAX_BACKOFF_SECONDS);
            }
        }

        $this->info('Stream stopped.');

        return self::SUCCESS;
    }

    private function trapSignals(): void
    {
        if (! extension_loaded('pcntl')) {
            return;
        }

        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, function (): void {
            $this->shouldQuit = true;
        });
        pcntl_signal(SIGINT, function (): void {
            $this->shouldQuit = true;
        });
    }

    /**
     * @param  Collection<string, Market>  $markets
     */
    private function subscribeAll(Client $client, Collection $markets): void
    {
        foreach ($markets as $market) {
            foreach (config('hyperliquid.intervals') as $interval) {
                $client->text(json_encode([
                    'method' => 'subscribe',
                    'subscription' => ['type' => 'candle', 'coin' => $market->symbol, 'interval' => $interval],
                ], JSON_THROW_ON_ERROR));
            }
        }

        $client->text(json_encode([
            'method' => 'subscribe',
            'subscription' => ['type' => 'allMids'],
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param  Collection<string, Market>  $markets
     */
    private function handleMessage(string $rawMessage, Collection $markets): void
    {
        $message = json_decode($rawMessage, true);

        if (! is_array($message) || ! isset($message['channel'])) {
            return;
        }

        match ($message['channel']) {
            'candle' => $this->handleCandle($message['data'] ?? [], $markets),
            'allMids' => $this->handleAllMids($message['data']['mids'] ?? []),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $candle
     * @param  Collection<string, Market>  $markets
     */
    private function handleCandle(array $candle, Collection $markets): void
    {
        $market = $markets->get($candle['s'] ?? null);

        if (! $market || ! isset($candle['i'], $candle['t'])) {
            return;
        }

        UpsertCandleJob::dispatch($market->id, $candle['i'], $candle);
    }

    /**
     * @param  array<string, mixed>  $mids
     */
    private function handleAllMids(array $mids): void
    {
        foreach ($mids as $symbol => $price) {
            Cache::put("hyperliquid:mid:{$symbol}", $price, self::MID_PRICE_TTL_SECONDS);
        }
    }

    /**
     * Hyperliquid doesn't backfill missed data on reconnect, so patch whatever
     * elapsed while we were disconnected via a normal REST backfill.
     *
     * @param  Collection<string, Market>  $markets
     */
    private function patchGap(Collection $markets, CarbonInterface $since): void
    {
        $fromMs = $since->getTimestampMs();
        $toMs = now()->getTimestampMs();

        foreach ($markets as $market) {
            foreach (config('hyperliquid.intervals') as $interval) {
                BackfillCandlesJob::dispatch($market->id, $interval, $fromMs, $toMs);
            }
        }
    }
}
