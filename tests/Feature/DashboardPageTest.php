<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('unauthenticated user is redirected to login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('authenticated user receives a 200 response', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk();
});

it('dashboard renders the Dashboard Inertia component', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->component('Dashboard'));
});

it('dashboard passes rangePairs as an Inertia prop', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->has('rangePairs'));
});

it('rangePairs contains three entries', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('rangePairs', fn ($pairs) => count($pairs) === 3));
});
