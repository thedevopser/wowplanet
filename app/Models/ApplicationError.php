<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Une erreur applicative, telle que la page de santé la montre.
 *
 * En base et non dans un fichier : la page lit une source que le serveur détermine, sans
 * jamais recevoir de chemin, et cette mémoire doit survivre à un Redis tombé — c'est
 * précisément là qu'on la cherche. Écrite par `DatabaseErrorHandler`, bornée aux
 * dernières entrées.
 *
 * @property int $id
 * @property string $level
 * @property string $message
 * @property string|null $exception_class
 * @property string|null $location
 * @property \Illuminate\Support\Carbon $occurred_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 */
class ApplicationError extends Model
{
    /** @use HasFactory<\Database\Factories\ApplicationErrorFactory> */
    use HasFactory;

    protected $table = 'application_errors';

    public $timestamps = false;

    protected $fillable = [
        'level',
        'message',
        'exception_class',
        'location',
        'occurred_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }
}
