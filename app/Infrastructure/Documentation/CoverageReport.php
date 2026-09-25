<?php

declare(strict_types=1);

namespace App\Infrastructure\Documentation;

/**
 * Résultat d'une mesure de couverture de documentation.
 */
final readonly class CoverageReport
{
    /**
     * @param  list<string>  $missing  Noms complets des classes documentées nulle part
     */
    public function __construct(
        public array $missing,
        public int $documented,
        public int $total,
        public int $excluded,
    ) {}

    /**
     * Un périmètre vide est couvert : c'est l'absence de classe à décrire, pas un échec.
     */
    public function percentage(): float
    {
        if ($this->total === 0) {
            return 100.0;
        }

        return round($this->documented * 100 / $this->total, 1);
    }

    /**
     * @return array<string, list<string>>
     */
    public function missingByLayer(): array
    {
        $grouped = [];

        foreach ($this->missing as $className) {
            $grouped[$this->layerOf($className)][] = $className;
        }

        return $grouped;
    }

    private function layerOf(string $className): string
    {
        $segments = explode('\\', $className);

        return $segments[1] ?? '?';
    }
}
