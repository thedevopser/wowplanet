<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureIsAdmin;
use Illuminate\Http\Request;

test('it rejects requests without the admin session flag', function (): void {
    $request = Request::create('/api/admin/status');
    $request->headers->set('Accept', 'application/json');
    $request->setLaravelSession(resolve(\Illuminate\Contracts\Session\Session::class));

    $response = (new EnsureIsAdmin)->handle($request, fn (): \Illuminate\Http\JsonResponse => response()->json(['ok' => true]));

    expect($response->getStatusCode())->toBe(403);
});

test('it sends a web visitor without the admin session flag back to the home page', function (): void {
    $request = Request::create('/admin/imports');
    $request->headers->set('Accept', 'text/html');
    $request->setLaravelSession(resolve(\Illuminate\Contracts\Session\Session::class));

    $response = (new EnsureIsAdmin)->handle($request, fn (): \Illuminate\Http\JsonResponse => response()->json(['ok' => true]));

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toBe(url('/'));
});

test('it lets admin sessions through', function (): void {
    $request = Request::create('/api/admin/status');
    $request->setLaravelSession(resolve(\Illuminate\Contracts\Session\Session::class));
    $request->session()->put('is_admin', true);

    $response = (new EnsureIsAdmin)->handle($request, fn (): \Illuminate\Http\JsonResponse => response()->json(['ok' => true]));

    expect($response->getStatusCode())->toBe(200);
});

test('it tells a web visitor turned away from the panel why, in a flash message', function (): void {
    $this->get('/admin/imports')
        ->assertRedirect('/')
        ->assertSessionHas('error', EnsureIsAdmin::REFUSAL_MESSAGE);
});

test('it keeps the refusal of an API call silent in the session', function (): void {
    $this->getJson('/api/admin/status')
        ->assertForbidden()
        ->assertSessionMissing('error');
});
