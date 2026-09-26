<?php

declare(strict_types=1);

namespace App\Infrastructure\Taxonomy;

use App\Models\WowDecor;
use App\Models\WowMount;
use App\Models\WowPet;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Les trois collections dont le rangement est de la curation et non de la donnée d'API.
 *
 * La valeur de chaque cas est le discriminant stocké dans `wow_collection_taxonomy` :
 * la changer invaliderait la taxonomie déjà en base.
 */
enum CollectionEntity: string
{
    case Mount = 'mount';
    case Pet = 'pet';
    case Decor = 'decor';

    public static function fromOption(string $name): self
    {
        $normalised = mb_strtolower(trim($name));

        return self::tryFrom($normalised) ?? throw new InvalidArgumentException(sprintf(
            'Entité de collection inconnue : %s. Entités disponibles : %s.',
            $name,
            implode(', ', array_column(self::cases(), 'value')),
        ));
    }

    /**
     * The catalogue table the site reads for this collection.
     *
     * @return Builder<WowMount>|Builder<WowPet>|Builder<WowDecor>
     */
    public function catalogue(): Builder
    {
        return match ($this) {
            self::Mount => WowMount::query(),
            self::Pet => WowPet::query(),
            self::Decor => WowDecor::query(),
        };
    }

    public function simpleArmoryFile(): string
    {
        return match ($this) {
            self::Mount => 'mounts.json',
            self::Pet => 'pets.json',
            self::Decor => 'decors.json',
        };
    }
}
