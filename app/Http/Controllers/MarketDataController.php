<?php

namespace App\Http\Controllers;

use App\Models\Candle;
use App\Models\Market;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class MarketDataController extends Controller
{
    public function index(Request $request): Response
    {
        $markets = Market::active()->orderBy('symbol')->get();

        $marketSummaries = $markets->map(function (Market $market) {
            $latestCandle = Candle::forMarket($market)->interval('1m')->latest('open_time')->first();

            return [
                'id' => $market->id,
                'symbol' => $market->symbol,
                'max_leverage' => $market->max_leverage,
                'last_price' => Cache::get("hyperliquid:mid:{$market->symbol}") ?? $latestCandle?->close,
                'candle_count' => Candle::forMarket($market)->count(),
                'updated_at_diff' => $latestCandle?->updated_at?->diffForHumans(),
            ];
        })->values();

        $intervals = config('hyperliquid.intervals');

        $selectedSymbol = $request->string('symbol')->toString() ?: $markets->first()?->symbol;
        $selectedInterval = $request->string('interval')->toString() ?: $intervals[0];

        $selectedMarket = $markets->firstWhere('symbol', $selectedSymbol);

        $candles = $selectedMarket
            ? Candle::forMarket($selectedMarket)
                ->interval($selectedInterval)
                ->orderByDesc('open_time')
                ->limit(100)
                ->get(['open_time', 'close_time', 'open', 'high', 'low', 'close', 'volume', 'is_closed'])
            : collect();

        return Inertia::render('MarketData', [
            'markets' => $marketSummaries,
            'intervals' => $intervals,
            'selectedSymbol' => $selectedSymbol,
            'selectedInterval' => $selectedInterval,
            'candles' => $candles,
        ]);
    }
}
