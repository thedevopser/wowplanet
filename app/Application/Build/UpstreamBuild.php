<?php

declare(strict_types=1);

namespace App\Application\Build;

use Illuminate\Support\Carbon;

/**
 * Ce qu'on sait du build d'un amont, et si on vient de l'apprendre ou de s'en souvenir.
 *
 * `reachable` et `build` se lisent ensemble : un build sans lecture aboutie est une
 * valeur qu'on garde faute de mieux, et le panneau doit le dire au lieu de la présenter
 * comme l'état du jour.
 */
final readonly class UpstreamBuild
{
    private function __construct(
        public UpstreamSource $source,
        public ?string $build,
        public ?Carbon $checkedAt,
        public bool $reachable,
    ) {}

    public static function read(UpstreamSource $upstreamSource, string $build, Carbon $checkedAt): self
    {
        return new self($upstreamSource, $build, $checkedAt, true);
    }

    /**
     * Le constructeur est privé parce qu'« aboutie, mais sans build » est un état qui
     * n'existe pas : le rendre inconstructible vaut mieux que le documenter.
     */
    public static function unreachable(UpstreamSource $upstreamSource, ?string $lastKnown, ?Carbon $checkedAt): self
    {
        return new self($upstreamSource, $lastKnown, $checkedAt, false);
    }

    /**
     * @return array{label: string, build: string|null, checked_at: string|null, reachable: bool}
     */
    public function toArray(): array
    {
        return [
            'label' => $this->source->label(),
            'build' => $this->build,
            'checked_at' => $this->checkedAt?->toIso8601String(),
            'reachable' => $this->reachable,
        ];
    }
}
