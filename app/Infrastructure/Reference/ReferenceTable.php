<?php

declare(strict_types=1);

namespace App\Infrastructure\Reference;

final readonly class ReferenceTable
{
    private const TABLE_PREFIX = 'wow_ref_';

    /**
     * @param  string  $source  Nom de la table DB2 chez wago, sensible à la casse
     * @param  list<ReferenceColumn>  $columns
     */
    public function __construct(
        public string $source,
        public string $slug,
        public ?string $locale,
        public array $columns,
    ) {}

    public function table(): string
    {
        return self::TABLE_PREFIX.$this->slug;
    }

    public function filename(string $build): string
    {
        return sprintf('%s-%s.csv', $this->slug, $build);
    }

    /**
     * @return list<string>
     */
    public function sourceHeaders(): array
    {
        return array_map(static fn (ReferenceColumn $referenceColumn): string => $referenceColumn->source, $this->columns);
    }

    /**
     * @return list<string>
     */
    public function targetColumns(): array
    {
        return array_map(static fn (ReferenceColumn $referenceColumn): string => $referenceColumn->target, $this->columns);
    }
}
