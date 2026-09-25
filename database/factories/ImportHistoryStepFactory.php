<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ImportHistoryStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportHistoryStep>
 */
class ImportHistoryStepFactory extends Factory
{
    protected $model = ImportHistoryStep::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stage' => 'mounts',
            'status' => 'completed',
            'created' => 0,
            'updated' => 0,
            'deleted' => 0,
            'api_calls' => 0,
            'duration_ms' => 0,
            'rows_after' => null,
            'error' => null,
        ];
    }
}
