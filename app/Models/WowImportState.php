<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Build WoW du dernier import réussi, par entité importée.
 *
 * En base et non en cache : c'est un état d'import, il doit survivre à un cache:clear.
 * Par entité et non globalement : un patch peut ne toucher que les recettes, et
 * réimporter les 22 000 apparences pour autant serait absurde.
 *
 * @property string $entity
 * @property string $build
 * @property string|null $last_modified
 * @property \Illuminate\Support\Carbon $imported_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 */
class WowImportState extends Model
{
    /** @use HasFactory<\Database\Factories\WowImportStateFactory> */
    use HasFactory;

    protected $table = 'wow_import_states';

    protected $primaryKey = 'entity';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'entity',
        'build',
        'last_modified',
        'imported_at',
    ];

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
