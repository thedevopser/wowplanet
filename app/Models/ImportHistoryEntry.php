<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
 * @property \Illuminate\Support\Carbon $started_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 * @property int $budget_used
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ImportHistoryStep> $steps
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 */
class ImportHistoryEntry extends Model
{
    /** @use HasFactory<\Database\Factories\ImportHistoryEntryFactory> */
    use HasFactory;

    protected $table = 'import_history';

    protected $primaryKey = 'job_id';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'job_id',
        'trigger',
        'mode',
        'status',
        'started_at',
        'finished_at',
        'budget_used',
    ];

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
