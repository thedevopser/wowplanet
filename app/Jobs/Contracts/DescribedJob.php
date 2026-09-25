<?php

declare(strict_types=1);

namespace App\Jobs\Contracts;

/**
 * Un job qui se présente dans la page Santé par un libellé et le compte qu'il sert.
 *
 * Ces deux valeurs sont recopiées en clair à la racine de la charge utile, sous
 * `described` : la page lit la file sans jamais ouvrir `data`, où un job peut porter
 * le jeton Blizzard d'un joueur.
 */
interface DescribedJob
{
    public const string PAYLOAD_KEY = 'described';

    public function label(): string;

    public function account(): ?string;
}
