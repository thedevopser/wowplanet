<?php

declare(strict_types=1);

namespace App\Models;

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
class WowAppearance extends Model
{
    /** @use HasFactory<\Database\Factories\WowAppearanceFactory> */
    use HasFactory;

    protected $table = 'wow_appearances';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $fillable = [
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
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
