<?php

use App\Models\Candle;
use App\Models\Market;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('it summarizes ingested markets and price history for the selected symbol', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $market = Market::create(['symbol' => 'BTC', 'sz_decimals' => 5, 'max_leverage' => 40]);

    Candle::create([
        'market_id' => $market->id,
        'interval' => '1h',
        'open_time' => 1_000_000,
        'close_time' => 3_599_999,
        'open' => '100',
        'high' => '120',
        'low' => '90',
        'close' => '110',
        'volume' => '1.5',
        'is_closed' => true,
    ]);
    Candle::create([
        'market_id' => $market->id,
        'interval' => '1h',
        'open_time' => 4_600_000,
        'close_time' => 7_199_999,
        'open' => '110',
        'high' => '130',
        'low' => '105',
        'close' => '121',
        'volume' => '2',
        'is_closed' => true,
    ]);

    $response = $this->get(route('dashboard', ['symbol' => 'BTC', 'interval' => '1h']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->where('stats.active_markets', 1)
        ->where('stats.total_candles', 2)
        ->where('selectedSymbol', 'BTC')
        ->has('markets', 1)
        ->where('markets.0.change_pct', 10)
        ->has('priceHistory', 2)
    );
});
