<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Taxonomy\CollectionEntity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Rangement curé d'une entrée de collection : sa catégorie de niveau 1 et sa source de niveau 2.
 *
 * C'est notre donnée, pas celle de l'API, qui n'expose qu'un vocabulaire de onze valeurs là où
 * la curation en compte 170 pour les seules montures. Amorcée une fois depuis SimpleArmory,
 * elle n'est ensuite qu'enrichie : un rafraîchissement ajoute les entrées inconnues et ne
 * touche jamais à une ligne existante, pour qu'un ajustement manuel y survive.
 *
 * Une ligne dont la catégorie est nulle est une entrée rangée nulle part **en connaissance de
 * cause** ; l'absence de ligne est une entrée à arbitrer. Ne pas confondre les deux.
 *
 * La clé primaire étant composite, toutes les écritures passent par `insertOrIgnore` au niveau
 * du constructeur de requête. Un `save()` sur une instance chargée ne saurait pas la retrouver.
 *
 * @property CollectionEntity $entity
 * @property int $entry_id
 * @property string|null $category
 * @property string|null $source
 * @property bool $obtainable
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 */
class WowCollectionTaxonomy extends Model
{
    /** @use HasFactory<\Database\Factories\WowCollectionTaxonomyFactory> */
    use HasFactory;

    protected $table = 'wow_collection_taxonomy';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'entity',
        'entry_id',
        'category',
        'source',
        'obtainable',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entity' => CollectionEntity::class,
            'entry_id' => 'integer',
            'obtainable' => 'boolean',
        ];
    }
}
