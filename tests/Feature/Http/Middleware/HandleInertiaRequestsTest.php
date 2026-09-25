<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;

test('a visitor gets an anonymous auth state', function (): void {
    $this->get('/faq')->assertInertia(fn (Assert $assert): Assert => $assert
        ->where('auth.isAuthenticated', false)
        ->where('auth.isAdmin', false)
        ->where('auth.battletag', ''));
});

test('a signed-in user gets the auth state of the session', function (): void {
    $this->withSession(['blizzard_user_token' => 'token', 'is_admin' => true, 'bnet_battletag' => 'Thrall#1234'])
        ->get('/faq')
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('auth.isAuthenticated', true)
            ->where('auth.isAdmin', true)
            ->where('auth.battletag', 'Thrall#1234'));
});

test('a malformed battletag in session is shared as empty', function (): void {
    $this->withSession(['blizzard_user_token' => 'token', 'bnet_battletag' => ['not', 'a', 'string']])
        ->get('/faq')
        ->assertInertia(fn (Assert $assert): Assert => $assert->where('auth.battletag', ''));
});

test('the sign-in request travels as a flash', function (): void {
    $this->withSession(['auth_required' => true])
        ->get('/faq')
        ->assertInertia(fn (Assert $assert): Assert => $assert->where('flash.authRequired', true));
});

test('no sign-in request is shared by default', function (): void {
    $this->get('/faq')->assertInertia(fn (Assert $assert): Assert => $assert->where('flash.authRequired', false));
});
