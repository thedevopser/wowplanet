<?php

declare(strict_types=1);

namespace App\Models;

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
class WowQuest extends Model
{
    /** @use HasFactory<\Database\Factories\WowQuestFactory> */
    use HasFactory;

    protected $table = 'wow_quests';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'name_fr',
        'expansion_id',
        'zone_name',
        'faction',
        'is_active',
    ];

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
