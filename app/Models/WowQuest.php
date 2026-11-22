<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WowQuestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name_fr
 * @property string|null $name_en
 * @property int $expansion_id
 * @property string $zone_name
 * @property string|null $zone_name_en
 * @property string|null $faction
 * @property bool $is_active
 * @property Carbon|null $mirror_unresolved_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @method static int count(string $columns = '*')
 */
#[Fillable([
    'id',
    'name_fr',
    'name_en',
    'expansion_id',
    'zone_name',
    'zone_name_en',
    'faction',
    'is_active',
])]
#[Table(name: 'wow_quests', key: 'id')]
#[WithoutIncrementing]
class WowQuest extends Model
{
    /** @use HasFactory<WowQuestFactory> */
    use HasFactory;

    /**
     * @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expansion_id' => 'integer',
            'is_active' => 'boolean',
            'mirror_unresolved_at' => 'datetime',
        ];
    }
}
