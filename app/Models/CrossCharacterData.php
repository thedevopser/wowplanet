<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * La colonne `data` est décodée par le cast : c'est une frontière, servie telle quelle au front et
 * relue par `CrossCharacterProgress::fromStored()` quand il faut la fusionner.
 *
 * @phpstan-type StoredCrossCharacterData array<string, mixed>
 *
 * @property string $bnet_user_id
 * @property StoredCrossCharacterData $data
 * @property int $character_count
 * @property Carbon|null $fetched_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 */
class CrossCharacterData extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $table = 'cross_character_data';

    protected $primaryKey = 'bnet_user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'bnet_user_id',
        'data',
        'character_count',
        'fetched_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'character_count' => 'integer',
            'fetched_at' => 'datetime',
        ];
    }
}
