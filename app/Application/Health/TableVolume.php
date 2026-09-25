<?php

declare(strict_types=1);

namespace App\Application\Health;

/**
 * Le poids d'une table, et ce qu'il dit de sa santé.
 *
 * Deux familles, deux lectures : une table du catalogue vide trahit un import raté, une
 * table applicative vide ne dit rien — personne n'a encore de favori, voilà tout.
 */
final readonly class TableVolume
{
    private const string CATALOGUE = 'catalogue';

    private const string APPLICATION = 'application';

    private function __construct(
        public string $table,
        private string $family,
        public int $rows,
        public ?int $active,
        public ?int $withoutIcon,
    ) {
        throw_if($rows < 0, \InvalidArgumentException::class, 'A row count cannot be negative.');
        throw_if($active !== null && ($active < 0 || $active > $rows), \InvalidArgumentException::class, 'Active rows must sit between zero and the row count.');
        throw_if($withoutIcon !== null && ($withoutIcon < 0 || $withoutIcon > $rows), \InvalidArgumentException::class, 'Iconless rows must sit between zero and the row count.');
    }

    /**
     * @param  int|null  $withoutIcon  Null pour une table qui ne porte pas d'icône
     */
    public static function catalogue(string $table, int $rows, int $active, ?int $withoutIcon): self
    {
        return new self($table, self::CATALOGUE, $rows, $active, $withoutIcon);
    }

    public static function application(string $table, int $rows): self
    {
        return new self($table, self::APPLICATION, $rows, null, null);
    }

    public function status(): HealthStatus
    {
        return $this->family === self::CATALOGUE && $this->rows === 0 ? HealthStatus::Critical : HealthStatus::Ok;
    }

    public function issue(): ?string
    {
        return $this->status() === HealthStatus::Critical ? 'Table du catalogue vide.' : null;
    }

    /**
     * @return array{table: string, family: string, rows: int, active: int|null, without_icon: int|null, status: string, issue: string|null}
     */
    public function toArray(): array
    {
        return [
            'table' => $this->table,
            'family' => $this->family,
            'rows' => $this->rows,
            'active' => $this->active,
            'without_icon' => $this->withoutIcon,
            'status' => $this->status()->value,
            'issue' => $this->issue(),
        ];
    }
}
