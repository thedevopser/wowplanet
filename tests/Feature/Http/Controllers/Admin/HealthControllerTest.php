<?php

declare(strict_types=1);

use App\Models\ApplicationError;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    useRedisQueue();
});

test('the health page renders AdminHealthPage for an administrator', function (): void {
    $this->withSession(['is_admin' => true])
        ->get('/admin/health')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert->component('AdminHealthPage'));
});

test('the health page carries every section of the diagnosis', function (): void {
    ApplicationError::factory()->create(['message' => 'Failed to fetch talents']);
    recordFailedJob();

    $this->withSession(['is_admin' => true])
        ->get('/admin/health')
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('health.services.0.service', 'postgresql')
            ->where('health.quota.status', 'ok')
            ->where('health.queue.status', 'warning')
            ->has('health.queue.failed', 1)
            ->where('health.volumes.status', 'critical')
            ->where('health.errors.entries.0.message', 'Failed to fetch talents'));
});

test('a section that cannot be measured does not keep the page from rendering', function (): void {
    Schema::drop('wow_mounts');

    $this->withSession(['is_admin' => true])
        ->get('/admin/health')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('health.volumes.status', 'unavailable')
            ->where('health.quota.status', 'ok'));
});
