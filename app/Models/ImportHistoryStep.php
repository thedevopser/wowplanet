<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une étape d'un import archivé : ce qu'elle a créé, mis à jour et supprimé, ce qu'elle a
 * coûté, et ce que ses tables pesaient à la clôture de l'import.
 *
 * @property int $id
 * @property string $job_id
 * @property string $stage
 * @property string $status
 * @property int $created
 * @property int $updated
 * @property int $deleted
 * @property int $api_calls
 * @property int $duration_ms
 * @property int|null $rows_after
 * @property string|null $error
 * @property-read ImportHistoryEntry $entry
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 */
class ImportHistoryStep extends Model
{
    /** @use HasFactory<\Database\Factories\ImportHistoryStepFactory> */
    use HasFactory;

    protected $table = 'import_history_steps';

    public $timestamps = false;

    protected $fillable = [
        'job_id',
        'stage',
        'status',
        'created',
        'updated',
        'deleted',
        'api_calls',
        'duration_ms',
        'rows_after',
        'error',
    ];

    /**
     * @return BelongsTo<ImportHistoryEntry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(ImportHistoryEntry::class, 'job_id', 'job_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created' => 'integer',
            'updated' => 'integer',
            'deleted' => 'integer',
            'api_calls' => 'integer',
            'duration_ms' => 'integer',
            'rows_after' => 'integer',
        ];
    }
}
