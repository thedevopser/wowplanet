<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WowImportStateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Build WoW du dernier import réussi, par entité importée.
 *
 * En base et non en cache : c'est un état d'import, il doit survivre à un cache:clear.
 * Par entité et non globalement : un patch peut ne toucher que les recettes, et
 * réimporter les 22 000 apparences pour autant serait absurde.
 *
 * @property string $entity
 * @property string $build
 * @property Carbon $imported_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 */
#[Fillable([
    'entity',
    'build',
    'imported_at',
])]
#[Table(name: 'wow_import_states', key: 'entity', keyType: 'string')]
#[WithoutIncrementing]
#[WithoutTimestamps]
class WowImportState extends Model
{
    /** @use HasFactory<WowImportStateFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'imported_at' => 'datetime',
        ];
    }
}
