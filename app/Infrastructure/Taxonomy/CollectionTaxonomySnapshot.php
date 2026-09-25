<?php

declare(strict_types=1);

namespace App\Infrastructure\Taxonomy;

use App\Infrastructure\Taxonomy\Exceptions\TaxonomySnapshotMalformedException;
use App\Infrastructure\Taxonomy\Exceptions\TaxonomySourceUnavailableException;

/**
 * Instantané versionné du rangement curé des collections (database/data/collection_taxonomy.csv).
 *
 * C'est la seule source dont l'amorçage dépend, et elle vit dans le dépôt : reconstruire la
 * taxonomie de zéro ne demande ni réseau ni tiers. SimpleArmory reste l'amont d'où vient la
 * curation d'un nouveau patch, mais l'instantané en est la trace, et c'est lui qui fait foi.
 *
 * Il est exporté depuis la base et non reconstruit depuis les fichiers curés : ceux-ci marquent
 * hors d'atteinte 301 montures et 131 mascottes que la base garde délibérément obtenables, et
 * réamorcer depuis eux changerait deux dénominateurs.
 *
 * Le format est un CSV de cinq colonnes plutôt qu'un JSON : le fichier reçoit des arbitrages
 * manuels, et une ligne par entrée se relit et se diffe, là où un objet imbriqué ne le fait pas.
 */
final readonly class CollectionTaxonomySnapshot
{
    public const string FILENAME = 'collection_taxonomy.csv';

    private const string TRUE = 'true';

    private const string FALSE = 'false';

    /**
     * PHP 8.4 déprécie l'échappement par barre oblique inverse hérité de `fgetcsv`, et son
     * défaut passera à celui-ci. Le fichier n'en contient aucune — ses seuls libellés
     * délicats portent une virgule, que les guillemets suffisent à protéger — donc le
     * fixer maintenant ne change rien à ce qui est déjà écrit et évite que chaque lecture
     * n'émette une dépréciation.
     */
    private const string NO_ESCAPE = '';

    /** @var list<string> */
    private const array HEADER = ['entity', 'entry_id', 'category', 'source', 'obtainable'];

    public function __construct(private ?string $path = null) {}

    public function path(): string
    {
        return $this->path ?? database_path('data/'.self::FILENAME);
    }

    /**
     * @return array<string, array<int, TaxonomyEntry>>
     */
    public function read(): array
    {
        $path = $this->path();

        if (! is_file($path)) {
            throw TaxonomySourceUnavailableException::for($path);
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw TaxonomySourceUnavailableException::for($path);
        }

        try {
            return $this->readEntries($handle, $path);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return array<int, TaxonomyEntry>
     */
    public function entriesFor(CollectionEntity $collectionEntity): array
    {
        return $this->read()[$collectionEntity->value] ?? [];
    }

    /**
     * Le contenu que `write()` déposerait, sans rien écrire : c'est ce qu'on télécharge
     * depuis un environnement dont le fichier ne peut pas être commité.
     *
     * @param  array<string, array<int, TaxonomyEntry>>  $entries
     */
    public function render(array $entries): string
    {
        $this->assertKnownEntities($entries);

        $handle = fopen('php://temp', 'w+b');
        throw_if($handle === false, \RuntimeException::class, 'No temporary stream to render the taxonomy snapshot.');

        try {
            $this->writeEntries($handle, $entries);
            rewind($handle);

            return (string) stream_get_contents($handle);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  array<string, array<int, TaxonomyEntry>>  $entries
     * @return int Nombre de lignes d'entrée écrites, en-tête exclu.
     */
    public function write(array $entries): int
    {
        $this->assertKnownEntities($entries);

        $path = $this->path();
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0o777, true);
        }

        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw TaxonomySourceUnavailableException::for($path);
        }

        try {
            return $this->writeEntries($handle, $entries);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     * @return array<string, array<int, TaxonomyEntry>>
     */
    private function readEntries($handle, string $path): array
    {
        $header = fgetcsv($handle, escape: self::NO_ESCAPE);

        if (! is_array($header)) {
            throw TaxonomySourceUnavailableException::for($path);
        }

        $found = array_map(static fn (?string $column): string => (string) $column, $header);

        if ($found !== self::HEADER) {
            throw TaxonomySnapshotMalformedException::header(self::HEADER, $found);
        }

        $entries = [];
        $line = 1;

        while (($row = fgetcsv($handle, escape: self::NO_ESCAPE)) !== false) {
            $line++;

            if ($row === [null]) {
                continue;
            }

            [$entity, $entry] = $this->parseRow($row, $line);
            $entries[$entity->value][$entry['id']] = $entry['entry'];
        }

        if ($entries === []) {
            throw TaxonomySourceUnavailableException::for($path);
        }

        return $entries;
    }

    /**
     * @param  list<string|null>  $row
     * @return array{0: CollectionEntity, 1: array{id: int, entry: TaxonomyEntry}}
     */
    private function parseRow(array $row, int $line): array
    {
        if (count($row) !== count(self::HEADER)) {
            throw TaxonomySnapshotMalformedException::columnCount($line, count(self::HEADER), count($row));
        }

        [$rawEntity, $rawEntryId, $rawCategory, $rawSource, $rawObtainable] = array_map(
            static fn (?string $value): string => (string) $value,
            $row,
        );

        $entity = CollectionEntity::tryFrom($rawEntity)
            ?? throw TaxonomySnapshotMalformedException::entity($line, $rawEntity);

        if (! ctype_digit($rawEntryId)) {
            throw TaxonomySnapshotMalformedException::entryId($line, $rawEntryId);
        }

        return [$entity, [
            'id' => (int) $rawEntryId,
            'entry' => new TaxonomyEntry(
                $rawCategory === '' ? null : $rawCategory,
                $rawSource === '' ? null : $rawSource,
                $this->parseObtainable($rawObtainable, $line),
            ),
        ]];
    }

    private function parseObtainable(string $raw, int $line): bool
    {
        return match ($raw) {
            self::TRUE => true,
            self::FALSE => false,
            default => throw TaxonomySnapshotMalformedException::obtainable($line, $raw),
        };
    }

    /**
     * @param  array<string, array<int, TaxonomyEntry>>  $entries
     */
    private function assertKnownEntities(array $entries): void
    {
        foreach (array_keys($entries) as $entity) {
            if (CollectionEntity::tryFrom($entity) === null) {
                throw TaxonomySnapshotMalformedException::writtenEntity($entity);
            }
        }
    }

    /**
     * @param  resource  $handle
     * @param  array<string, array<int, TaxonomyEntry>>  $entries
     */
    private function writeEntries($handle, array $entries): int
    {
        fputcsv($handle, self::HEADER, escape: self::NO_ESCAPE);
        $written = 0;

        foreach (CollectionEntity::cases() as $collectionEntity) {
            $entityEntries = $entries[$collectionEntity->value] ?? [];
            ksort($entityEntries);

            foreach ($entityEntries as $entryId => $taxonomyEntry) {
                fputcsv($handle, [
                    $collectionEntity->value,
                    (string) $entryId,
                    $taxonomyEntry->category ?? '',
                    $taxonomyEntry->source ?? '',
                    $taxonomyEntry->obtainable ? self::TRUE : self::FALSE,
                ], escape: self::NO_ESCAPE);
                $written++;
            }
        }

        return $written;
    }
}
