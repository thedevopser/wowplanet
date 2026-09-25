<?php

declare(strict_types=1);

namespace App\Models;

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
class WowMount extends Model
{
    /** @use HasFactory<\Database\Factories\WowMountFactory> */
    use HasFactory;

    protected $table = 'wow_mounts';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'name_fr',
        'source',
        'category',
        'source_spell_id',
        'icon_url',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
