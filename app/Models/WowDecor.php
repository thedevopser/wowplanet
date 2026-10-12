<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WowDecorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name_fr
 * @property string|null $category
 * @property string|null $source
 * @property int|null $item_id
 * @property string|null $icon_url
 * @property bool $is_active
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @method static int count(string $columns = '*')
 */
#[Fillable([
    'id',
    'name_fr',
    'category',
    'source',
    'item_id',
    'icon_url',
    'is_active',
])]
#[Table(name: 'wow_decors', key: 'id')]
#[WithoutIncrementing]
class WowDecor extends Model
{
    /** @use HasFactory<WowDecorFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
