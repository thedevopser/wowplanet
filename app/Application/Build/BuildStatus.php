<?php

declare(strict_types=1);

namespace App\Application\Build;

use App\Application\Import\ImportStage;
use App\Application\Import\StageFreshness;
use App\Application\Reference\ReferenceBuildState;
use App\Models\WowImportState;

/**
 * L'écart entre ce que les amonts servent et ce que WowPlanet a importé, entité par
 * entité, tel que le bandeau du tableau de bord le montre.
 *
 * Deux affirmations distinctes en sortent, et il ne faut pas les confondre : « tout est
 * à jour » et « la vérification a abouti ». Un amont muet interdit la première, quel que
 * soit l'état de la base — annoncer que tout va bien sur la foi d'un appel raté est
 * exactement ce qu'on cherche à éviter.
 */
final readonly class BuildStatus
{
    private const STATE_CURRENT = 'current';

    private const STATE_STALE = 'stale';

    private const STATE_NEVER = 'never';

    private const STATE_UNKNOWN = 'unknown';

    public function __construct(
        private UpstreamBuildProbe $upstreamBuildProbe,
        private StageFreshness $stageFreshness,
        private ReferenceBuildState $referenceBuildState,
    ) {}

    /**
     * @return array{
     *     upstreams: array{
     *         blizzard: array{label: string, build: string|null, checked_at: string|null, reachable: bool},
     *         wago: array{label: string, build: string|null, checked_at: string|null, reachable: bool}
     *     },
     *     entries: list<array{stage: string, label: string, upstream: string, build: string|null, upstream_build: string|null, imported_at: string|null, state: string, note: string|null}>,
     *     behind: list<string>,
     *     is_up_to_date: bool,
     *     is_conclusive: bool
     * }
     */
    public function snapshot(bool $force = false): array
    {
        [$blizzard, $wago] = $this->readUpstreams($force);

        $entries = $this->entries($blizzard, $wago);
        $behind = array_values(array_map(
            static fn (array $entry): string => $entry['stage'],
            array_filter(
                $entries,
                // Une entité jamais importée est en retard au sens de l'exploitant ; une
                // entité qu'on n'a pas pu situer ne l'est pas, faute de le savoir.
                static fn (array $entry): bool => in_array($entry['state'], [self::STATE_STALE, self::STATE_NEVER], true),
            ),
        ));

        $isConclusive = $blizzard->reachable && $wago->reachable;

        return [
            'upstreams' => [
                'blizzard' => $blizzard->toArray(),
                'wago' => $wago->toArray(),
            ],
            'entries' => $entries,
            'behind' => $behind,
            'is_up_to_date' => $isConclusive && $behind === [],
            'is_conclusive' => $isConclusive,
        ];
    }

    /**
     * Les deux amonts sont lus ensemble et rendus ensemble : un instantané qui mêlerait
     * une lecture fraîche et une lecture d'il y a une heure dirait deux états à la fois.
     *
     * @return array{UpstreamBuild, UpstreamBuild}
     */
    private function readUpstreams(bool $force): array
    {
        return [
            $this->upstreamBuildProbe->current(UpstreamSource::Blizzard, $force),
            $this->upstreamBuildProbe->current(UpstreamSource::Wago, $force),
        ];
    }

    /**
     * @return list<array{stage: string, label: string, upstream: string, build: string|null, upstream_build: string|null, imported_at: string|null, state: string, note: string|null}>
     */
    private function entries(UpstreamBuild $blizzard, UpstreamBuild $wago): array
    {
        $states = WowImportState::query()->get()->keyBy('entity');
        $socle = $this->referenceBuildState->current($wago->reachable ? $wago->build : null);

        return array_map(function (ImportStage $importStage) use ($blizzard, $wago, $states, $socle): array {
            $isReference = $importStage === ImportStage::Reference;
            $upstream = $isReference ? $wago : $blizzard;

            /** @var WowImportState|null $state */
            $state = $states->get($importStage->value);

            // L'état du socle se lit sur l'inventaire de ses chargements, pas sur la ligne
            // d'import : une synchronisation lancée depuis sa propre page n'en écrit pas.
            $build = $isReference ? $socle['build'] : $state?->build;
            $importedAt = $isReference ? $socle['loaded_at'] : $state?->imported_at->toIso8601String();

            return [
                'stage' => $importStage->value,
                'label' => $importStage->label(),
                'upstream' => $upstream->source->value,
                'build' => $build,
                // Le build de l'amont est répété sur chaque ligne bien qu'il soit
                // déductible : deux familles de numéros cohabitent à l'écran, et rien à
                // rapprocher côté client signifie aucune occasion de les croiser.
                'upstream_build' => $upstream->build,
                'imported_at' => $importedAt,
                'state' => $this->state($importStage, $blizzard, $wago, $build),
                // Le socle est la seule entité que son build ne décrit pas entièrement :
                // partiellement rechargé, son dernier chargement est au build servi alors
                // qu'une table est restée derrière. La ligne afficherait deux builds
                // identiques sous une alerte de retard sans cette précision.
                'note' => $isReference ? $this->socleNote($socle) : null,
            ];
        }, ImportStage::chain());
    }

    /**
     * @param  array{build: string|null, loaded_at: string|null, tables_behind: int, tables_total: int}  $socle
     */
    private function socleNote(array $socle): ?string
    {
        if ($socle['tables_behind'] === 0) {
            return null;
        }

        return sprintf(
            '%d table%s sur %d en retard',
            $socle['tables_behind'],
            $socle['tables_behind'] > 1 ? 's' : '',
            $socle['tables_total'],
        );
    }

    private function state(ImportStage $importStage, UpstreamBuild $blizzard, UpstreamBuild $wago, ?string $build): string
    {
        $upstream = $importStage === ImportStage::Reference ? $wago : $blizzard;

        return match (true) {
            ! $upstream->reachable => self::STATE_UNKNOWN,
            $build === null => self::STATE_NEVER,
            $this->stageFreshness->isUpToDate($importStage, $blizzard->build, $wago->build) => self::STATE_CURRENT,
            default => self::STATE_STALE,
        };
    }
}
