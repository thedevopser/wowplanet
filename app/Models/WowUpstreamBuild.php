<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WowUpstreamBuildFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Dernier build connu de chaque amont, avec la date à laquelle il a été lu.
 *
 * À ne pas confondre avec `WowImportState`, qui porte le build d'un import réussi :
 * celui-ci porte le build que l'amont sert. C'est contre lui que le panneau situe
 * l'autre.
 *
 * En base et non en cache : c'est la mémoire de secours d'une panne amont, et le
 * panneau sait vider les caches. Perdre cette valeur au moment précis où elle sert
 * serait le contraire de ce qu'on en attend.
 *
 * @property string $source
 * @property string|null $build
 * @property Carbon|null $checked_at
 * @property string $outcome
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 */
#[Fillable([
    'source',
    'build',
    'checked_at',
    'outcome',
])]
#[Table(name: 'wow_upstream_builds', key: 'source', keyType: 'string')]
#[WithoutIncrementing]
#[WithoutTimestamps]
class WowUpstreamBuild extends Model
{
    /** @use HasFactory<WowUpstreamBuildFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
        ];
    }
}
