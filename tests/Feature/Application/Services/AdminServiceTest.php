<?php

declare(strict_types=1);

use App\Application\Services\AdminService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->adminService = resolve(AdminService::class);
});

test('clearing the caches runs the four purges and hands back what they said', function (): void {
    $called = [];
    Artisan::shouldReceive('call')->times(4)->andReturnUsing(function (string $command) use (&$called): int {
        $called[] = $command;

        return 0;
    });
    Artisan::shouldReceive('output')->andReturn('vidé.');

    expect($this->adminService->clearCaches())->toBe('vidé.vidé.vidé.vidé.')
        ->and($called)->toBe(['config:clear', 'route:clear', 'view:clear', 'cache:clear']);
});

test('taking the application down passes the bypass secret along', function (): void {
    Artisan::shouldReceive('call')->once()->with('down', ['--secret' => 'un-secret-long'])->andReturn(0);
    Artisan::shouldReceive('output')->andReturn('');

    $this->adminService->toggleMaintenance(true, 'un-secret-long');
});

test('taking the application down without a secret leaves the option out', function (): void {
    Artisan::shouldReceive('call')->once()->with('down', [])->andReturn(0);
    Artisan::shouldReceive('output')->andReturn('');

    $this->adminService->toggleMaintenance(true);
});

test('an empty secret is no secret at all', function (): void {
    Artisan::shouldReceive('call')->once()->with('down', [])->andReturn(0);
    Artisan::shouldReceive('output')->andReturn('');

    $this->adminService->toggleMaintenance(true, '');
});

test('bringing the application back up takes no option', function (): void {
    Artisan::shouldReceive('call')->once()->with('up')->andReturn(0);
    Artisan::shouldReceive('output')->andReturn('');

    $this->adminService->toggleMaintenance(false);
});

test('it reports the application as online outside maintenance', function (): void {
    expect($this->adminService->isInMaintenance())->toBeFalse();
});

test('an announcement goes to the webhook of the channel it names', function (): void {
    config(['services.discord.webhook_changelog' => 'https://discord.test/changelog']);
    Http::fake(['discord.test/*' => Http::response('', 204)]);

    $sent = $this->adminService->sendDiscordEmbed('changelog', ['title' => 'Titre', 'description' => 'Corps']);

    expect($sent)->toBeTrue();
    Http::assertSent(fn ($request): bool => $request->url() === 'https://discord.test/changelog'
        && $request['embeds'][0]['title'] === 'Titre');
});

test('an announcement to the discussion channel takes the other webhook', function (): void {
    config(['services.discord.webhook_discussion' => 'https://discord.test/discussion']);
    Http::fake(['discord.test/*' => Http::response('', 204)]);

    $this->adminService->sendDiscordEmbed('discussion', ['title' => 'Titre', 'description' => 'Corps']);

    Http::assertSent(fn ($request): bool => $request->url() === 'https://discord.test/discussion');
});

test('a webhook that refuses the announcement is reported as a failure, not as a success', function (): void {
    config(['services.discord.webhook_changelog' => 'https://discord.test/changelog']);
    Http::fake(['discord.test/*' => Http::response('nope', 500)]);

    expect($this->adminService->sendDiscordEmbed('changelog', ['title' => 'Titre', 'description' => 'Corps']))->toBeFalse();
});

test('an unknown channel is refused rather than sent somewhere at random', function (): void {
    $this->adminService->sendDiscordEmbed('salon-secret', ['title' => 'Titre', 'description' => 'Corps']);
})->throws(InvalidArgumentException::class);

test('a channel without a configured webhook is refused', function (): void {
    config(['services.discord.webhook_changelog' => '']);

    $this->adminService->sendDiscordEmbed('changelog', ['title' => 'Titre', 'description' => 'Corps']);
})->throws(RuntimeException::class);

test('a queue that refuses the job releases the lock instead of blocking the panel', function (): void {
    $this->mock(Illuminate\Contracts\Bus\Dispatcher::class)
        ->shouldReceive('dispatch')->andThrow(new RuntimeException('file injoignable'));

    expect(fn (): string => $this->adminService->startImport(true, [], false, 'console'))
        ->toThrow(RuntimeException::class);

    expect(resolve(App\Application\Import\CurrentImport::class)->jobId())->toBeNull();
});

test('a queue that refuses a socle sync releases the lock too', function (): void {
    $this->mock(Illuminate\Contracts\Bus\Dispatcher::class)
        ->shouldReceive('dispatch')->andThrow(new RuntimeException('file injoignable'));

    expect(fn (): string => $this->adminService->startReferenceSync(null))
        ->toThrow(RuntimeException::class);

    expect(resolve(App\Application\Import\CurrentImport::class)->jobId())->toBeNull();
});
