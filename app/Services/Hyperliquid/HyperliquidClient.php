<?php

namespace App\Services\Hyperliquid;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

class HyperliquidClient
{
    private const RATE_LIMIT_KEY = 'hyperliquid:rest:weight';

    private string $baseUrl;

    public function __construct()
    {
        $network = config('hyperliquid.network');

        $this->baseUrl = config("hyperliquid.base_urls.{$network}");
    }

    /**
     * Perp universe + per-coin metadata (max leverage, size decimals, ...).
     *
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return $this->request('meta', config('hyperliquid.rate_limit.weights.meta'));
    }

    /**
     * Current mid price for every coin.
     *
     * @return array<string, string>
     */
    public function allMids(): array
    {
        return $this->request('allMids', config('hyperliquid.rate_limit.weights.allMids'));
    }

    /**
     * Historical OHLCV candles. Hyperliquid caps this at 5000 candles per call.
     *
     * @return array<int, array<string, mixed>>
     */
    public function candleSnapshot(string $coin, string $interval, int $startTimeMs, int $endTimeMs): array
    {
        $response = $this->request('candleSnapshot', config('hyperliquid.rate_limit.weights.candleSnapshot_base'), [
            'req' => [
                'coin' => $coin,
                'interval' => $interval,
                'startTime' => $startTimeMs,
                'endTime' => $endTimeMs,
            ],
        ]);

        // True up the rate-limit budget now that we know how many candles came back.
        $extraWeight = (int) floor(count($response) / 60) * config('hyperliquid.rate_limit.weights.candleSnapshot_per_60_candles');

        if ($extraWeight > 0) {
            $this->consumeWeight($extraWeight);
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<mixed>
     */
    private function request(string $type, int $weight, array $payload = []): array
    {
        $this->guardRateLimit($weight);

        $response = Http::baseUrl($this->baseUrl)
            ->timeout(config('hyperliquid.http.timeout'))
            ->retry(
                config('hyperliquid.http.retry_times'),
                config('hyperliquid.http.retry_sleep_ms'),
            )
            ->post('/info', array_merge(['type' => $type], $payload))
            ->throw();

        return $response->json();
    }

    private function guardRateLimit(int $weight): void
    {
        $budget = (int) (config('hyperliquid.rate_limit.weight_per_minute') * config('hyperliquid.rate_limit.safety_margin'));

        if (RateLimiter::tooManyAttempts(self::RATE_LIMIT_KEY, $budget)) {
            throw new HyperliquidRateLimitException(RateLimiter::availableIn(self::RATE_LIMIT_KEY));
        }

        $this->consumeWeight($weight);
    }

    private function consumeWeight(int $weight): void
    {
        for ($i = 0; $i < $weight; $i++) {
            RateLimiter::hit(self::RATE_LIMIT_KEY, config('hyperliquid.rate_limit.decay_seconds'));
        }
    }
}
