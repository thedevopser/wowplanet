<?php

declare(strict_types=1);

namespace App\Application\Build;

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Reference\WagoClient;
use App\Models\WowUpstreamBuild;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use RuntimeException;

/**
 * Le build que chaque amont sert aujourd'hui, avec deux mémoires distinctes.
 *
 * Le cache d'une heure évite qu'une requête sortante parte à chaque ouverture de page,
 * pour une valeur qui change au rythme des patchs. La base, elle, retient la dernière
 * valeur lue : c'est ce qui permet d'afficher quelque chose quand l'amont ne répond
 * plus, et de le dater honnêtement. Un échec n'écrit jamais le cache, la vue suivante
 * retente donc immédiatement.
 */
final readonly class UpstreamBuildProbe
{
    private const TTL_S = 3600;

    private const OUTCOME_OK = 'ok';

    private const OUTCOME_UNREACHABLE = 'unreachable';

    public function __construct(
        private BlizzardApiClient $blizzardApiClient,
        private WagoClient $wagoClient,
    ) {}

    public function current(UpstreamSource $upstreamSource, bool $force = false): UpstreamBuild
    {
        $known = WowUpstreamBuild::query()->find($upstreamSource->value);
        $knownAt = $known?->checked_at;

        /** @var string|null $cached */
        $cached = $force ? null : Cache::get($upstreamSource->cacheKey());

        if (is_string($cached) && $cached !== '') {
            // Un succès seul écrit le cache : une valeur servie de là vient donc d'une
            // lecture aboutie dans l'heure.
            return UpstreamBuild::read($upstreamSource, $cached, $knownAt ?? Date::now());
        }

        $build = $this->read($upstreamSource);

        if ($build === null) {
            $this->recordFailure($upstreamSource);

            return UpstreamBuild::unreachable($upstreamSource, $known?->build, $knownAt);
        }

        $checkedAt = Date::now();

        Cache::put($upstreamSource->cacheKey(), $build, self::TTL_S);
        $this->recordSuccess($upstreamSource, $build, $checkedAt);

        return UpstreamBuild::read($upstreamSource, $build, $checkedAt);
    }

    /**
     * Trois familles d'échec, aucune redondante : Guzzle pour l'API Blizzard, la panne de
     * connexion du client HTTP de Laravel pour wago, et le `RuntimeException` que lèvent
     * aussi bien un jeton Battle.net refusé qu'un wago illisible.
     */
    private function read(UpstreamSource $upstreamSource): ?string
    {
        try {
            return match ($upstreamSource) {
                UpstreamSource::Blizzard => $this->blizzardApiClient->currentBuild(),
                UpstreamSource::Wago => $this->wagoClient->liveBuild(),
            };
        } catch (GuzzleException|ConnectionException|RuntimeException) {
            return null;
        }
    }

    private function recordSuccess(UpstreamSource $upstreamSource, string $build, Carbon $checkedAt): void
    {
        WowUpstreamBuild::query()->updateOrCreate(['source' => $upstreamSource->value], ['build' => $build, 'checked_at' => $checkedAt, 'outcome' => self::OUTCOME_OK]);
    }

    /**
     * Un échec ne touche ni au build ni à sa date : il ne dit rien de l'amont, seulement
     * qu'on n'a pas pu lui parler.
     */
    private function recordFailure(UpstreamSource $upstreamSource): void
    {
        WowUpstreamBuild::query()->updateOrCreate(['source' => $upstreamSource->value], ['outcome' => self::OUTCOME_UNREACHABLE]);
    }
}
