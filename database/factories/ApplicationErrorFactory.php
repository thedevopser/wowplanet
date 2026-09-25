<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ApplicationError;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationError>
 */
class ApplicationErrorFactory extends Factory
{
    protected $model = ApplicationError::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'level' => 'ERROR',
            'message' => fake()->sentence(),
            'exception_class' => null,
            'location' => null,
            'occurred_at' => now(),
        ];
    }
}
