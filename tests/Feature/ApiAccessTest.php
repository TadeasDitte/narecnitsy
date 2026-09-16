<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('api-access.index'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the api access page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('api-access.index'));
    $response->assertOk();
});

test('users can generate and revoke a bot token', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post(route('api-access.tokens.store'), ['name' => 'test-bot'])
        ->assertRedirect();

    expect($user->tokens()->count())->toBe(1);

    $token = $user->tokens()->first();

    $this->delete(route('api-access.tokens.destroy', $token->id))
        ->assertRedirect();

    expect($user->tokens()->count())->toBe(0);
});
