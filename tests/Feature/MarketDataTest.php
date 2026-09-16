<?php

use App\Models\Candle;
use App\Models\Market;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('market-data.index'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the market data page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('market-data.index'));
    $response->assertOk();
});

test('it shows ingested markets and candles for the selected symbol', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $market = Market::create(['symbol' => 'BTC', 'sz_decimals' => 5, 'max_leverage' => 40]);
    Candle::create([
        'market_id' => $market->id,
        'interval' => '1m',
        'open_time' => 1_000_000,
        'close_time' => 1_059_999,
        'open' => '100',
        'high' => '110',
        'low' => '90',
        'close' => '105',
        'volume' => '1.5',
        'is_closed' => true,
    ]);

    $response = $this->get(route('market-data.index', ['symbol' => 'BTC', 'interval' => '1m']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('MarketData')
        ->where('selectedSymbol', 'BTC')
        ->has('markets', 1)
        ->has('candles', 1)
    );
});
