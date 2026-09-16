<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('login'));
});

test('authenticated users are redirected to market data', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('home'));

    $response->assertRedirect(route('market-data.index'));
});
