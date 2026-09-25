<?php

declare(strict_types=1);

use App\Models\WowImportState;
use App\Models\WowMount;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

test('the imports page renders AdminImportsPage for an administrator', function (): void {
    $this->withSession(['is_admin' => true])
        ->get('/admin/imports')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert->component('AdminImportsPage'));
});

test('the page lists the seven catalogue entities', function (): void {
    $this->withSession(['is_admin' => true])
        ->get('/admin/imports')
        ->assertInertia(fn (Assert $assert): Assert => $assert->has('entities', 7));
});

test('the page carries what each entity weighs and when it was last imported', function (): void {
    WowMount::query()->insert([['id' => 1, 'name_fr' => 'Cheval']]);
    WowImportState::query()->create([
        'entity' => 'mounts',
        'build' => '12.1.0_68914',
        'imported_at' => now()->subDay(),
    ]);

    $this->withSession(['is_admin' => true])
        ->get('/admin/imports')
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('entities.3.stage', 'mounts')
            ->where('entities.3.rows', 1)
            ->where('entities.3.build', '12.1.0_68914')
            ->etc());
});

test('the reference socle is not offered among the entities of this page', function (): void {
    $this->withSession(['is_admin' => true])
        ->get('/admin/imports')
        ->assertInertia(fn (Assert $assert): Assert => $assert->where(
            'entities',
            fn (Collection $entities): bool => $entities->pluck('stage')->doesntContain('reference'),
        )->etc());
});
