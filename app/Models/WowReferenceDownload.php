<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Inventaire du magasin de fichiers de référence : un fichier téléchargé, une ligne.
 *
 * Les lignes s'accumulent d'un build à l'autre, l'epic `refonte-panel-admin` s'appuyant
 * dessus pour inventorier et purger le magasin. La volumétrie du dernier chargement sert
 * ici de garde-fou : une source qui s'effondre est refusée avant d'écraser le socle.
 *
 * @property string $filename
 * @property string $source_table
 * @property string $build
 * @property int $bytes
 * @property int $row_count
 * @property \Illuminate\Support\Carbon $downloaded_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 */
class WowReferenceDownload extends Model
{
    /** @use HasFactory<\Database\Factories\WowReferenceDownloadFactory> */
    use HasFactory;

    protected $table = 'wow_reference_downloads';

    protected $primaryKey = 'filename';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'filename',
        'source_table',
        'build',
        'bytes',
        'row_count',
        'downloaded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bytes' => 'integer',
            'row_count' => 'integer',
            'downloaded_at' => 'datetime',
        ];
    }
}
