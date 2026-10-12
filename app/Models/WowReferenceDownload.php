<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WowReferenceDownloadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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
 * @property Carbon $downloaded_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 */
#[Fillable([
    'filename',
    'source_table',
    'build',
    'bytes',
    'row_count',
    'downloaded_at',
])]
#[Table(name: 'wow_reference_downloads', key: 'filename', keyType: 'string')]
#[WithoutIncrementing]
#[WithoutTimestamps]
class WowReferenceDownload extends Model
{
    /** @use HasFactory<WowReferenceDownloadFactory> */
    use HasFactory;

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
