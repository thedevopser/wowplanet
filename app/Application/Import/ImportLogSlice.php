<?php

declare(strict_types=1);

namespace App\Application\Import;

/**
 * Ce qu'un lecteur du journal reçoit : les lignes apparues depuis sa position, et la
 * position à présenter au prochain appel.
 *
 * Le curseur rendu est toujours la longueur du journal, jamais la position demandée
 * augmentée du nombre de lignes lues : un lecteur qui présente un curseur en avance sur
 * le journal se retrouve recalé dessus au lieu de s'en éloigner davantage.
 */
final readonly class ImportLogSlice
{
    /**
     * @param  list<string>  $lines
     */
    public function __construct(
        public array $lines,
        public int $cursor,
    ) {}

    /**
     * @return array{cursor: int, lines: list<string>}
     */
    public function toArray(): array
    {
        return [
            'cursor' => $this->cursor,
            'lines' => $this->lines,
        ];
    }
}
