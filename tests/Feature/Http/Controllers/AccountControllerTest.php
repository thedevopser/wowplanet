<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;

dataset('hub views', [
    'characters' => ['/mon-compte', 'personnages'],
    'score' => ['/mon-compte/score', 'score'],
    'classes' => ['/mon-compte/classes', 'classes'],
]);

test('renders the account hub on the requested view when authenticated', function (string $url, string $view): void {
    $this->withSession(['blizzard_user_token' => 'fake-token'])
        ->get($url)
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert->component('AccountPage')->where('view', $view));
})->with('hub views');

test('redirects to home asking to sign in when not authenticated', function (string $url): void {
    $this->get($url)
        ->assertRedirect('/')
        ->assertSessionHas('auth_required', true);
})->with('hub views');

test('remembers the requested view before asking to sign in', function (string $url): void {
    $this->get($url.'?tri=niveau')
        ->assertSessionHas('url.intended', $url.'?tri=niveau');
})->with('hub views');

test('renders the not found page for an unknown view', function (): void {
    $this->withSession(['blizzard_user_token' => 'fake-token'])
        ->get('/mon-compte/inventaire')
        ->assertNotFound()
        ->assertInertia(fn (Assert $assert): Assert => $assert->component('NotFoundPage'));
});

test('redirects the former account pages permanently to their view', function (string $from, string $to): void {
    $this->get($from)->assertStatus(301)->assertRedirect($to);
})->with([
    ['/my-characters', '/mon-compte'],
    ['/my-score', '/mon-compte/score'],
    ['/class-stats', '/mon-compte/classes'],
]);
