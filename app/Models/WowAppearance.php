<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WowAppearanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name_fr
 * @property string|null $slot
 * @property string|null $category
 * @property int|null $quality
 * @property int|null $item_id
 * @property int|null $icon_file_data_id
 * @property string|null $icon_url
 * @property int|null $expansion_id
 * @property string|null $source
 * @property bool $is_active
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @method static int count(string $columns = '*')
 */
#[Fillable([
    'id',
    'name_fr',
    'slot',
    'category',
    'quality',
    'item_id',
    'icon_file_data_id',
    'icon_url',
    'expansion_id',
    'source',
    'is_active',
])]
#[Table(name: 'wow_appearances', key: 'id')]
#[WithoutIncrementing]
class WowAppearance extends Model
{
    /** @use HasFactory<WowAppearanceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
