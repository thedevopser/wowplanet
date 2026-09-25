<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;

test('the tools page renders AdminToolsPage for an administrator', function (): void {
    $this->withSession(['is_admin' => true])
        ->get('/admin/tools')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert->component('AdminToolsPage'));
});
