<?php

declare(strict_types=1);

use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;

test('the primitives showcase has no route outside local development', function (): void {
    expect(Route::getRoutes()->match(request()->create('/ui'))->uri())->toBe('{any}');

    $this->get('/ui')->assertNotFound();
});

test('the primitives showcase is served in local development', function (): void {
    $this->app['env'] = 'local';
    Route::setRoutes(new RouteCollection);
    Route::middleware('web')->group(base_path('routes/web.php'));

    $this->get('/ui')->assertOk()->assertInertia(
        fn (AssertableInertia $assertableInertia): AssertableInertia => $assertableInertia->component('UiShowcasePage')
    );
});
