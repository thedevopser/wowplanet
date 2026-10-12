<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ImportHistoryEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un import de la chaîne, tel que l'historique le garde : qui l'a lancé, comment, et
 * comment il s'est terminé.
 *
 * En base et non en cache : c'est de la donnée d'exploitation, qui doit survivre à un
 * `cache:clear` comme à un redémarrage de Redis. Le journal détaillé, lui, reste dans
 * Redis et expire au bout d'une journée ; le rapport, ici, est gardé douze mois.
 *
 * @property string $job_id
 * @property string $trigger
 * @property string $mode
 * @property string $status
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property int $budget_used
 * @property-read Collection<int, ImportHistoryStep> $steps
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 */
#[Fillable([
    'job_id',
    'trigger',
    'mode',
    'status',
    'started_at',
    'finished_at',
    'budget_used',
])]
#[Table(name: 'import_history', key: 'job_id', keyType: 'string')]
#[WithoutIncrementing]
#[WithoutTimestamps]
class ImportHistoryEntry extends Model
{
    /** @use HasFactory<ImportHistoryEntryFactory> */
    use HasFactory;

    /**
     * @return HasMany<ImportHistoryStep, $this>
     */
    public function steps(): HasMany
    {
        // Dans l'ordre où l'import les a déroulées, qui est celui de la chaîne.
        return $this->hasMany(ImportHistoryStep::class, 'job_id', 'job_id')->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'budget_used' => 'integer',
        ];
    }
}
