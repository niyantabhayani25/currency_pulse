<?php

declare(strict_types=1);

use App\Models\Currency;
use App\Models\User;
use App\Models\UserCurrency;
use App\Services\FrankfurterService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Unauthenticated ───────────────────────────────────────────────────────────

it('returns 401 for unauthenticated index request', function () {
    $this->getJson('/api/currencies')->assertUnauthorized();
});

it('returns 401 for unauthenticated update request', function () {
    $this->putJson('/api/currencies', [])->assertUnauthorized();
});

// ── GET /api/currencies ───────────────────────────────────────────────────────

it('index returns currencies, selected_ids, and rates keys', function () {
    $user = User::factory()->create();
    Currency::factory()->eur()->create();

    $this->mock(FrankfurterService::class, function ($mock) {
        $mock->shouldReceive('getLatestRates')->andReturn(['EUR' => 1.08]);
    });

    $this->actingAs($user)
        ->getJson('/api/currencies')
        ->assertOk()
        ->assertJsonStructure(['currencies', 'selected_ids', 'rates']);
});

it('index returns null rates when Frankfurter is unavailable', function () {
    $user     = User::factory()->create();
    $currency = Currency::factory()->eur()->create();
    UserCurrency::create(['user_id' => $user->id, 'currency_id' => $currency->id]);

    $this->mock(FrankfurterService::class, function ($mock) {
        $mock->shouldReceive('getLatestRates')->andReturn(null);
    });

    $this->actingAs($user)
        ->getJson('/api/currencies')
        ->assertOk()
        ->assertJson(['rates' => null]);
});

it('index returns the authenticated users selected currency ids', function () {
    $user     = User::factory()->create();
    $currency = Currency::factory()->eur()->create();
    UserCurrency::create(['user_id' => $user->id, 'currency_id' => $currency->id]);

    $this->mock(FrankfurterService::class, function ($mock) {
        $mock->shouldReceive('getLatestRates')->andReturn(['EUR' => 1.08]);
    });

    $this->actingAs($user)
        ->getJson('/api/currencies')
        ->assertOk()
        ->assertJson(['selected_ids' => [$currency->id]]);
});

// ── PUT /api/currencies ───────────────────────────────────────────────────────

it('update syncs the currency selection', function () {
    $user      = User::factory()->create();
    $currency1 = Currency::factory()->create(['code' => 'EUR', 'name' => 'Euro']);
    $currency2 = Currency::factory()->create(['code' => 'GBP', 'name' => 'British Pound']);

    $this->actingAs($user)
        ->putJson('/api/currencies', ['currency_ids' => [$currency1->id, $currency2->id]])
        ->assertOk()
        ->assertJson(['selected_ids' => [$currency1->id, $currency2->id]]);

    expect(UserCurrency::where('user_id', $user->id)->count())->toBe(2);
});

it('update replaces the previous selection entirely', function () {
    $user      = User::factory()->create();
    $currency1 = Currency::factory()->create(['code' => 'EUR', 'name' => 'Euro']);
    $currency2 = Currency::factory()->create(['code' => 'GBP', 'name' => 'British Pound']);
    UserCurrency::create(['user_id' => $user->id, 'currency_id' => $currency1->id]);

    $this->actingAs($user)
        ->putJson('/api/currencies', ['currency_ids' => [$currency2->id]])
        ->assertOk();

    expect(UserCurrency::where('user_id', $user->id)->where('currency_id', $currency1->id)->exists())->toBeFalse();
    expect(UserCurrency::where('user_id', $user->id)->where('currency_id', $currency2->id)->exists())->toBeTrue();
});

it('update rejects more than 5 currencies', function () {
    $user      = User::factory()->create();
    $currencies = Currency::factory()->count(6)->sequence(
        ['code' => 'EUR', 'name' => 'Euro'],
        ['code' => 'GBP', 'name' => 'British Pound'],
        ['code' => 'JPY', 'name' => 'Japanese Yen'],
        ['code' => 'AUD', 'name' => 'Australian Dollar'],
        ['code' => 'CAD', 'name' => 'Canadian Dollar'],
        ['code' => 'CHF', 'name' => 'Swiss Franc'],
    )->create();

    $this->actingAs($user)
        ->putJson('/api/currencies', ['currency_ids' => $currencies->pluck('id')->all()])
        ->assertUnprocessable();
});

it('update rejects a currency id that does not exist', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson('/api/currencies', ['currency_ids' => [999999]])
        ->assertUnprocessable();
});
