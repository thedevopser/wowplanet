<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\Import\ImportHistoryReader;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class HistoryController extends Controller
{
    public function __construct(private readonly ImportHistoryReader $importHistoryReader) {}

    public function page(Request $request): InertiaResponse
    {
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);

        return Inertia::render('AdminHistoryPage', [
            'history' => $this->importHistoryReader->page(max(1, $request->integer('page', 1))),
        ]);
    }

    public function entry(string $jobId): InertiaResponse
    {
        return Inertia::render('AdminHistoryEntryPage', [
            'entry' => $this->importHistoryReader->entry($jobId),
        ]);
    }

    public function compare(Request $request): InertiaResponse
    {
        $request->validate([
            'first' => ['required', 'string'],
            'second' => ['required', 'string', 'different:first'],
        ]);

        return Inertia::render('AdminHistoryComparePage', [
            'comparison' => $this->importHistoryReader->compare($request->string('first')->value(), $request->string('second')->value()),
        ]);
    }
}
