<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
 * @property \Illuminate\Support\Carbon|null $checked_at
 * @property string $outcome
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 */
class WowUpstreamBuild extends Model
{
    /** @use HasFactory<\Database\Factories\WowUpstreamBuildFactory> */
    use HasFactory;

    protected $table = 'wow_upstream_builds';

    protected $primaryKey = 'source';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'source',
        'build',
        'checked_at',
        'outcome',
    ];

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
