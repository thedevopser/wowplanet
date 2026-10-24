<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WowProfessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name_fr
 * @property string $type
 * @property array<int, int>|null $max_skill_levels
 * @property bool $is_active
 * @property-read int $active_recipes_count Only present when loaded through `withCount()`.
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @method static int count(string $columns = '*')
 */
#[Fillable([
    'id',
    'name_fr',
    'type',
    'max_skill_levels',
    'is_active',
])]
#[Table(name: 'wow_professions', key: 'id')]
#[WithoutIncrementing]
class WowProfession extends Model
{
    /** @use HasFactory<WowProfessionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_skill_levels' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<WowRecipe, $this>
     */
    public function recipes(): HasMany
    {
        return $this->hasMany(WowRecipe::class, 'profession_id');
    }

    /**
     * Métiers consultables dans la base publique : l'Archéologie reste active pour la
     * fiche personnage, mais l'API ne lui donne aucune recette à présenter.
     *
     * @param  Builder<static>  $builder
     */
    #[Scope]
    protected function withActiveRecipes(Builder $builder): void
    {
        $builder->where('is_active', true)
            ->whereHas('recipes', fn (Builder $builder) => $builder->where('is_active', true));
    }
}
