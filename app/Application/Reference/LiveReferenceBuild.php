<?php

declare(strict_types=1);

namespace App\Application\Reference;

use App\Application\Build\UpstreamBuildProbe;
use App\Application\Build\UpstreamSource;

/**
 * Le build que wago sert aujourd'hui, contre lequel le panneau situe l'état du socle.
 *
 * C'est bien le build de wago, et non celui de l'API Blizzard : le socle vient des tables
 * DB2, et les deux numéros ne coïncident pas — Blizzard sert un build en retard sur celui
 * que wago publie. Comparer le socle au build Blizzard signalerait un écart permanent qui
 * n'existe pas.
 *
 * La lecture et son cache d'une heure vivent dans `UpstreamBuildProbe`, commun aux deux
 * amonts. Ce qui reste ici est le contrat propre à la page du socle : une comparaison,
 * ou rien.
 */
final readonly class LiveReferenceBuild
{
    public function __construct(private UpstreamBuildProbe $upstreamBuildProbe) {}

    /**
     * `null` quand wago ne répond pas — y compris lorsqu'un build plus ancien reste connu.
     * Situer le socle contre une valeur périmée le dirait à jour sur la foi d'un appel
     * raté ; la pire réaction à une panne amont serait une page d'administration en
     * erreur, l'avant-dernière serait une page qui ment.
     */
    public function current(): ?string
    {
        $upstreamBuild = $this->upstreamBuildProbe->current(UpstreamSource::Wago);

        return $upstreamBuild->reachable ? $upstreamBuild->build : null;
    }
}
