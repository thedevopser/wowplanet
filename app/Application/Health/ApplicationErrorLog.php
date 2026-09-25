<?php

declare(strict_types=1);

namespace App\Application\Health;

use App\Models\ApplicationError;

/**
 * Les dernières erreurs applicatives, lues dans le journal que tient `DatabaseErrorHandler`.
 *
 * La source est fixée ici, côté serveur : la page ne désigne ni fichier ni canal, et ne
 * devient donc jamais un lecteur de journaux paramétrable.
 */
final readonly class ApplicationErrorLog
{
    /**
     * @return list<array{id: int, level: string, message: string, exception_class: string|null, location: string|null, occurred_at: string}>
     */
    public function latest(int $limit): array
    {
        throw_if($limit < 1, \InvalidArgumentException::class, 'At least one error must be asked for.');

        return array_values(ApplicationError::query()
            ->latest('occurred_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(static fn (ApplicationError $applicationError): array => [
                'id' => $applicationError->id,
                'level' => $applicationError->level,
                'message' => $applicationError->message,
                'exception_class' => $applicationError->exception_class,
                'location' => $applicationError->location,
                'occurred_at' => $applicationError->occurred_at->toIso8601String(),
            ])
            ->all());
    }
}
