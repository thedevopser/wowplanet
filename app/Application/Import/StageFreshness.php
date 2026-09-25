<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Application\Reference\ReferenceBuildState;
use App\Infrastructure\Blizzard\ImportBuildGate;

/**
 * Dit si une étape est déjà à jour, chaque famille contre son propre amont.
 *
 * Le catalogue vient de l'API Blizzard, le socle des tables DB2 de wago, et les deux
 * numéros de build ne coïncident pas. Juger le socle sur le build Blizzard le ferait
 * passer pour à jour tant que Blizzard ne bouge pas, alors même que wago a publié un
 * nouveau build : le panneau signalerait l'écart, l'import sauterait l'étape.
 *
 * C'est le seul endroit qui connaît cette règle, et le bandeau du tableau de bord comme
 * `ImportPipeline` l'appellent tous les deux. Ils ne peuvent donc pas diverger, et c'est
 * ce qui rend honnête le bouton de mise à jour.
 *
 * Les builds entrent en paramètre plutôt que d'être lus ici : les lire coûte deux
 * requêtes sortantes, qui n'ont leur place ni dans une boucle d'import ni dans le rendu
 * d'une page.
 */
final readonly class StageFreshness
{
    public function __construct(
        private ImportBuildGate $importBuildGate,
        private ReferenceBuildState $referenceBuildState,
    ) {}

    /**
     * @param  string|null  $blizzardBuild  Build servi par l'API Blizzard, `null` s'il n'a pas pu être lu
     * @param  string|null  $wagoBuild  Build servi par wago, `null` s'il n'a pas pu être lu
     */
    public function isUpToDate(ImportStage $importStage, ?string $blizzardBuild, ?string $wagoBuild): bool
    {
        if ($importStage !== ImportStage::Reference) {
            return $this->importBuildGate->isUpToDate($importStage->value, $blizzardBuild);
        }

        return $wagoBuild !== null
            && $this->referenceBuildState->current($wagoBuild)['tables_behind'] === 0;
    }
}
