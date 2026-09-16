<?php

namespace App\Jobs;

use App\Models\Candle;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpsertCandleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $marketId,
        public string $interval,
        public array $payload,
    ) {}

    public function handle(): void
    {
        Candle::updateOrCreate(
            [
                'market_id' => $this->marketId,
                'interval' => $this->interval,
                'open_time' => $this->payload['t'],
            ],
            [
                'close_time' => $this->payload['T'],
                'open' => $this->payload['o'],
                'high' => $this->payload['h'],
                'low' => $this->payload['l'],
                'close' => $this->payload['c'],
                'volume' => $this->payload['v'],
                'num_trades' => $this->payload['n'] ?? null,
                'is_closed' => $this->payload['T'] <= now()->getTimestampMs(),
            ]
        );
    }
}
