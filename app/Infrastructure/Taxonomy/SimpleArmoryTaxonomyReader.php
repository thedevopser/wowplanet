<?php

declare(strict_types=1);

namespace App\Infrastructure\Taxonomy;

use App\Infrastructure\Blizzard\Responses\Exceptions\BlizzardContractException;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;
use App\Infrastructure\Reference\ReferenceStore;
use App\Infrastructure\Taxonomy\Exceptions\TaxonomySourceUnavailableException;
use Illuminate\Support\Facades\Storage;

/**
 * Lecture d'un export curé de SimpleArmory, réduite au rangement qu'on en tire.
 *
 * Blizzard n'expose nulle part la catégorie ni la source d'une monture, d'une mascotte ou d'une
 * décoration : cette curation humaine est le seul amont possible, et elle ne se consulte qu'à la
 * main, une fois par patch. Aucun import ne passe ici — le chemin d'exécution lit l'instantané
 * versionné, pas ces fichiers.
 *
 * Le dédoublonnage garde la **dernière** occurrence d'un identifiant listé sous plusieurs
 * catégories. C'est ce qu'a fait chaque import jusqu'ici, donc ce qui a produit le rangement en
 * place : prendre la première ferait dériver plusieurs dizaines d'entrées.
 */
final readonly class SimpleArmoryTaxonomyReader
{
    /**
     * @return array<int, TaxonomyEntry>
     */
    public function entriesFor(CollectionEntity $collectionEntity): array
    {
        $filename = $collectionEntity->simpleArmoryFile();
        $entries = [];

        try {
            foreach ($this->categories($filename) as $responsePayload) {
                foreach ($this->entriesInCategory($responsePayload) as $entryId => $taxonomyEntry) {
                    $entries[$entryId] = $taxonomyEntry;
                }
            }
        } catch (BlizzardContractException $blizzardContractException) {
            throw TaxonomySourceUnavailableException::malformed($blizzardContractException);
        }

        if ($entries === []) {
            throw TaxonomySourceUnavailableException::for($filename);
        }

        return $entries;
    }

    /**
     * Une catégorie qui n'est pas un objet est ignorée plutôt que refusée : l'export est curé à
     * la main, et une coquille isolée ne doit pas priver le reste du rangement.
     *
     * @return list<ResponsePayload>
     */
    private function categories(string $filename): array
    {
        $disk = Storage::disk(ReferenceStore::DISK);

        if (! $disk->exists($filename)) {
            throw TaxonomySourceUnavailableException::for($filename);
        }

        try {
            $decoded = json_decode((string) $disk->get($filename), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw TaxonomySourceUnavailableException::for($filename);
        }

        if (! is_array($decoded) || isset($decoded['supercats'])) {
            throw TaxonomySourceUnavailableException::for($filename);
        }

        $categories = [];
        foreach ($decoded as $index => $category) {
            if (is_array($category)) {
                $categories[] = ResponsePayload::forEndpoint(sprintf('%s, category %s', $filename, $index), $category);
            }
        }

        return $categories;
    }

    /**
     * @return array<int, TaxonomyEntry>
     */
    private function entriesInCategory(ResponsePayload $responsePayload): array
    {
        $categoryName = $this->label($responsePayload->lenientString('name'));
        $entries = [];

        foreach ($responsePayload->objectList('subcats') as $subcategory) {
            $sourceName = $this->label($subcategory->lenientString('name'));

            foreach ($subcategory->objectList('items') as $item) {
                $entryId = $this->entryId($item);

                if ($entryId === null) {
                    continue;
                }

                $entries[$entryId] = new TaxonomyEntry(
                    $categoryName,
                    $sourceName,
                    $item->optionalBool('notObtainable') !== true,
                );
            }
        }

        return $entries;
    }

    private function entryId(ResponsePayload $responsePayload): ?int
    {
        if ($responsePayload->optionalBool('notReleased') === true) {
            return null;
        }

        $entryId = $responsePayload->lenientInt('ID') ?? 0;

        return $entryId > 0 ? $entryId : null;
    }

    private function label(?string $value): ?string
    {
        $trimmed = trim($value ?? '');

        return $trimmed !== '' ? $trimmed : null;
    }
}
