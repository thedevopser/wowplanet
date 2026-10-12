<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CharacterFavoriteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $bnet_user_id
 * @property string $realm_slug
 * @property string $character_name
 * @property int $sort_order
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 */
#[Fillable([
    'bnet_user_id',
    'realm_slug',
    'character_name',
    'sort_order',
])]
class CharacterFavorite extends Model
{
    /** @use HasFactory<CharacterFavoriteFactory> */
    use HasFactory;
}
