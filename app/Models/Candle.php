<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Candle extends Model
{
    protected $fillable = [
        'market_id',
        'interval',
        'open_time',
        'close_time',
        'open',
        'high',
        'low',
        'close',
        'volume',
        'num_trades',
        'is_closed',
    ];

    protected function casts(): array
    {
        return [
            'open_time' => 'integer',
            'close_time' => 'integer',
            'open' => 'decimal:8',
            'high' => 'decimal:8',
            'low' => 'decimal:8',
            'close' => 'decimal:8',
            'volume' => 'decimal:8',
            'num_trades' => 'integer',
            'is_closed' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Market, $this>
     */
    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    /**
     * @param  Builder<Candle>  $query
     * @return Builder<Candle>
     */
    public function scopeForMarket(Builder $query, Market $market): Builder
    {
        return $query->where('market_id', $market->id);
    }

    /**
     * @param  Builder<Candle>  $query
     * @return Builder<Candle>
     */
    public function scopeInterval(Builder $query, string $interval): Builder
    {
        return $query->where('interval', $interval);
    }

    /**
     * @param  Builder<Candle>  $query
     * @return Builder<Candle>
     */
    public function scopeBetween(Builder $query, int $fromMs, int $toMs): Builder
    {
        return $query->whereBetween('open_time', [$fromMs, $toMs]);
    }
}
