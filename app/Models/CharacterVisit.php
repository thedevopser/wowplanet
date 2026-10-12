<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\CharacterVisitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
 * @property Carbon $last_visited_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 */
#[Fillable([
    'realm_slug',
    'character_name',
    'display_name',
    'display_realm',
    'class_name',
    'level',
    'last_visited_at',
])]
class CharacterVisit extends Model
{
    /** @use HasFactory<CharacterVisitFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'last_visited_at' => 'datetime',
        ];
    }
}
