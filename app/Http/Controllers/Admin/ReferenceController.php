<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\Reference\LiveReferenceBuild;
use App\Application\Reference\ReferenceFileInventory;
use App\Application\Reference\ReferenceInventory;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ReferenceController extends Controller
{
    public function __construct(
        private readonly ReferenceInventory $referenceInventory,
        private readonly ReferenceFileInventory $referenceFileInventory,
        private readonly LiveReferenceBuild $liveReferenceBuild,
    ) {}

    public function page(): InertiaResponse
    {
        $liveBuild = $this->liveReferenceBuild->current();

        return Inertia::render('AdminReferencePage', [
            'tables' => $this->referenceInventory->entries($liveBuild),
            'store' => $this->referenceFileInventory->contents(),
            'liveBuild' => $liveBuild,
        ]);
    }
}
