<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Market extends Model
{
    protected $fillable = [
        'symbol',
        'sz_decimals',
        'max_leverage',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * @return HasMany<Candle, $this>
     */
    public function candles(): HasMany
    {
        return $this->hasMany(Candle::class);
    }

    /**
     * @param  Builder<Market>  $query
     * @return Builder<Market>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
