<?php

namespace App\Http\Controllers;

use App\Models\Candle;
use App\Models\Market;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const SPARKLINE_POINTS = 60;

    private const SPARKLINE_INTERVAL = '1h';

    private const PRICE_HISTORY_POINTS = 200;

    public function index(Request $request): Response
    {
        $markets = Market::active()->orderBy('symbol')->get();
        $intervals = config('hyperliquid.intervals');

        $latestCandle = Candle::latest('updated_at')->first();

        $selectedSymbol = $request->string('symbol')->toString() ?: $markets->first()?->symbol;
        $selectedInterval = $request->string('interval')->toString() ?: ($intervals[0] ?? self::SPARKLINE_INTERVAL);
        $selectedMarket = $markets->firstWhere('symbol', $selectedSymbol);

        return Inertia::render('Dashboard', [
            'stats' => [
                'active_markets' => $markets->count(),
                'total_candles' => Candle::count(),
                'tracked_intervals' => count($intervals),
                'last_update_diff' => $latestCandle?->updated_at?->diffForHumans(),
            ],
            'markets' => $markets->map(fn (Market $market) => $this->marketWidget($market))->values(),
            'intervals' => $intervals,
            'selectedSymbol' => $selectedSymbol,
            'selectedInterval' => $selectedInterval,
            'priceHistory' => $selectedMarket ? $this->priceHistory($selectedMarket, $selectedInterval) : [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function marketWidget(Market $market): array
    {
        $series = Candle::forMarket($market)
            ->interval(self::SPARKLINE_INTERVAL)
            ->orderByDesc('open_time')
            ->limit(self::SPARKLINE_POINTS)
            ->get(['close'])
            ->reverse()
            ->values()
            ->map(fn (Candle $candle) => (float) $candle->close);

        $latestClose = $series->last();
        $firstClose = $series->first();

        $changePct = ($firstClose && $latestClose && $firstClose != 0.0)
            ? round((($latestClose - $firstClose) / $firstClose) * 100, 2)
            : null;

        return [
            'symbol' => $market->symbol,
            'last_price' => Cache::get("hyperliquid:mid:{$market->symbol}") ?? $latestClose,
            'change_pct' => $changePct,
            'series' => $series,
        ];
    }

    /**
     * @return Collection<int, array{t: int<0, max>, v: float}>
     */
    private function priceHistory(Market $market, string $interval): Collection
    {
        return Candle::forMarket($market)
            ->interval($interval)
            ->orderByDesc('open_time')
            ->limit(self::PRICE_HISTORY_POINTS)
            ->get(['open_time', 'close'])
            ->reverse()
            ->values()
            ->map(fn (Candle $candle) => [
                't' => $candle->open_time,
                'v' => (float) $candle->close,
            ]);
    }
}
