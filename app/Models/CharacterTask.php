<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $bnet_user_id
 * @property string $realm_slug
 * @property string $character_name
 * @property string $name
 * @property string $reset_type
 * @property bool $is_completed
 * @property \Carbon\Carbon|null $completed_at
 * @property int $sort_order
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 */
class CharacterTask extends Model
{
    /** @use HasFactory<\Database\Factories\CharacterTaskFactory> */
    use HasFactory;

    protected $fillable = [
        'bnet_user_id',
        'realm_slug',
        'character_name',
        'name',
        'reset_type',
        'is_completed',
        'completed_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }
}
