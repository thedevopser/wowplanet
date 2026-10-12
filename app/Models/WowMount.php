<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WowMountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name_fr
 * @property string|null $source
 * @property string|null $category
 * @property int|null $source_spell_id
 * @property string|null $icon_url
 * @property bool $is_active
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @method static int count(string $columns = '*')
 */
#[Fillable([
    'id',
    'name_fr',
    'source',
    'category',
    'source_spell_id',
    'icon_url',
    'is_active',
])]
#[Table(name: 'wow_mounts', key: 'id')]
#[WithoutIncrementing]
class WowMount extends Model
{
    /** @use HasFactory<WowMountFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
