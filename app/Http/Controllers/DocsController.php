<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class DocsController extends Controller
{
    public function index(): Response
    {
        abort_unless(app()->isLocal(), 404);

        return response()->view('docs');
    }

    public function file(string $path): Response
    {
        abort_unless(app()->isLocal(), 404);

        abort_if(str_contains($path, '..') || ! str_ends_with($path, '.md'), 404);

        $fullPath = base_path('documentation/'.$path);

        abort_unless(file_exists($fullPath), 404);

        return response((string) file_get_contents($fullPath), 200)
            ->header('Content-Type', 'text/plain; charset=utf-8');
    }
}
