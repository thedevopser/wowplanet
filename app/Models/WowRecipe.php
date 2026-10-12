<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WowRecipeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $name_fr
 * @property int $profession_id
 * @property int $expansion_id
 * @property string|null $category_name
 * @property string|null $faction
 * @property int|null $wowhead_spell_id
 * @property bool $is_active
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @method static int count(string $columns = '*')
 */
#[Fillable([
    'id',
    'name_fr',
    'profession_id',
    'expansion_id',
    'category_name',
    'faction',
    'wowhead_spell_id',
    'is_active',
])]
#[Table(name: 'wow_recipes', key: 'id')]
#[WithoutIncrementing]
class WowRecipe extends Model
{
    /** @use HasFactory<WowRecipeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'profession_id' => 'integer',
            'expansion_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<WowProfession, $this>
     */
    public function profession(): BelongsTo
    {
        return $this->belongsTo(WowProfession::class, 'profession_id');
    }
}
