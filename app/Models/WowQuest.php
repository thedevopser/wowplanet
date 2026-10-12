<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WowQuestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name_fr
 * @property int $expansion_id
 * @property string $zone_name
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
    'zone_name',
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
        ];
    }
}
