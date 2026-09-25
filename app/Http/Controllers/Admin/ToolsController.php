<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ToolsController extends Controller
{
    public function page(): InertiaResponse
    {
        return Inertia::render('AdminToolsPage');
    }
}
