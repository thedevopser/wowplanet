<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\Session;

trait ResolvesBnetUser
{
    private function getAuthenticatedUserId(): ?string
    {
        if (! Session::has('blizzard_user_token')) {
            return null;
        }

        /** @var string|null */
        return Session::get('bnet_user_id');
    }
}
