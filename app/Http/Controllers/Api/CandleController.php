<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Candle;
use App\Models\Market;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CandleController extends Controller
{
    private const MAX_ROWS = 5000;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'symbol' => ['required', 'string'],
            'interval' => ['required', 'string', 'in:'.implode(',', config('hyperliquid.intervals'))],
            'from' => ['nullable', 'integer'],
            'to' => ['nullable', 'integer'],
        ]);

        $market = Market::where('symbol', $validated['symbol'])->firstOrFail();

        $candles = Candle::forMarket($market)
            ->interval($validated['interval'])
            ->when(
                isset($validated['from']) || isset($validated['to']),
                fn ($query) => $query->between(
                    $validated['from'] ?? 0,
                    $validated['to'] ?? now()->getTimestampMs(),
                )
            )
            ->orderBy('open_time')
            ->limit(self::MAX_ROWS)
            ->get();

        return response()->json($candles);
    }
}
