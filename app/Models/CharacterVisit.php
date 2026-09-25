<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $realm_slug
 * @property string $character_name
 * @property string $display_name
 * @property string $display_realm
 * @property string $class_name
 * @property int $level
 * @property \Carbon\Carbon $last_visited_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 */
class CharacterVisit extends Model
{
    /** @use HasFactory<\Database\Factories\CharacterVisitFactory> */
    use HasFactory;

    protected $fillable = [
        'realm_slug',
        'character_name',
        'display_name',
        'display_realm',
        'class_name',
        'level',
        'last_visited_at',
    ];

    protected function casts(): array
    {
        return [
            'last_visited_at' => 'datetime',
        ];
    }
}
