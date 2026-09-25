<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ImportHistoryEntry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ImportHistoryEntry>
 */
class ImportHistoryEntryFactory extends Factory
{
    protected $model = ImportHistoryEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_id' => (string) Str::uuid(),
            'trigger' => 'console',
            'mode' => 'incremental',
            'status' => 'completed',
            'started_at' => now()->subHour(),
            'finished_at' => now(),
            'budget_used' => 0,
        ];
    }
}
