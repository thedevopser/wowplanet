<?php

declare(strict_types=1);

namespace App\Application\Taxonomy;

use App\Infrastructure\Taxonomy\CollectionEntity;

/**
 * Un arbitrage nomme des entrées que le catalogue de la collection ne porte pas : rien
 * n'est rangé, puisque le rangement serait recopié sur une ligne qui n'existe pas.
 */
final class UnknownCollectionEntryException extends \RuntimeException
{
    /**
     * @param  list<int>  $entryIds
     */
    private function __construct(string $message, public readonly array $entryIds)
    {
        parent::__construct($message);
    }

    /**
     * @param  list<int>  $entryIds
     */
    public static function in(CollectionEntity $collectionEntity, array $entryIds): self
    {
        return new self(sprintf(
            'Certaines entrées ne sont pas au catalogue de cette collection (%s) : %s.',
            $collectionEntity->value,
            implode(', ', $entryIds),
        ), $entryIds);
    }
}
