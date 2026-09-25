<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Reference\ReferenceCatalog;
use App\Infrastructure\Reference\ReferenceTable;
use App\Models\WowReferenceDownload;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WowReferenceDownload>
 */
class WowReferenceDownloadFactory extends Factory
{
    protected $model = WowReferenceDownload::class;

    /**
     * Le nom de fichier est dérivé des attributs résolus, et par le catalogue.
     *
     * Une table dont le slug n'est pas son nom source en minuscules — `AreaTable` devient
     * `area_table` — sortait sinon un nom que le magasin n'écrit jamais. Tant que rien
     * n'appariait disque et inventaire la divergence passait inaperçue ; la purge
     * apparie par nom de fichier, qui est la clé primaire du modèle.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_table' => fake()->randomElement((new ReferenceCatalog)->sources()),
            'build' => sprintf('12.1.0.%d', fake()->numberBetween(60000, 69999)),
            'filename' => $this->filenameFor(...),
            'bytes' => fake()->numberBetween(1000, 5_000_000),
            'row_count' => fake()->numberBetween(100, 20000),
            'downloaded_at' => now(),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function filenameFor(array $attributes): string
    {
        $source = is_string($attributes['source_table'] ?? null) ? $attributes['source_table'] : '';
        $build = is_string($attributes['build'] ?? null) ? $attributes['build'] : '';

        $referenceTable = (new ReferenceCatalog)->find($source);

        return $referenceTable instanceof ReferenceTable
            ? $referenceTable->filename($build)
            : sprintf('%s-%s.csv', mb_strtolower($source), $build);
    }
}
