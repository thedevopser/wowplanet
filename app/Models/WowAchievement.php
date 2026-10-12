<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WowAchievementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name_fr
 * @property int $expansion_id
 * @property string $category_name
 * @property string|null $icon_url
 * @property int $points
 * @property string|null $faction
 * @property bool $is_active
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @method static int count(string $columns = '*')
 */
#[Fillable([
    'id',
    'name_fr',
    'expansion_id',
    'category_name',
    'icon_url',
    'points',
    'faction',
    'is_active',
])]
#[Table(name: 'wow_achievements', key: 'id')]
#[WithoutIncrementing]
class WowAchievement extends Model
{
    /** @use HasFactory<WowAchievementFactory> */
    use HasFactory;

    /**
     * @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expansion_id' => 'integer',
            'points' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
