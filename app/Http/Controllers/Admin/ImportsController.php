<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\Import\ImportInventory;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ImportsController extends Controller
{
    public function __construct(private readonly ImportInventory $importInventory) {}

    public function page(): InertiaResponse
    {
        return Inertia::render('AdminImportsPage', [
            'entities' => $this->importInventory->entries(),
        ]);
    }
}
