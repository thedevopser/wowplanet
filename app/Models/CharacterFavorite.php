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
 * @property int $sort_order
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 */
class CharacterFavorite extends Model
{
    /** @use HasFactory<\Database\Factories\CharacterFavoriteFactory> */
    use HasFactory;

    protected $fillable = [
        'bnet_user_id',
        'realm_slug',
        'character_name',
        'sort_order',
    ];
}
