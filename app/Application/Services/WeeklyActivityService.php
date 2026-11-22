<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Services\Progress\RaidProgressAggregator;
use App\Application\Services\Week\WeeklyActivityBuilder;
use App\Domain\Services\WeeklyReset;
use App\Http\Localization\AppLocale;
use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\RaidJournal;
use App\Infrastructure\Blizzard\Responses\Profile\CharacterRaidsResponse;
use App\Infrastructure\Blizzard\Responses\Profile\MythicKeystoneProfileResponse;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;
use App\Jobs\ComputeWeeklyActivityJob;
use DateTimeImmutable;
use GuzzleHttp\Exception\ResponseException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

/**
 * La semaine du compte : ce qu'il reste à faire à chaque personnage avant la remise à zéro.
 *
 * Le calcul tourne dans un job, lancé une seule fois et suivi par son identifiant. Il lit deux
 * réponses de profil par personnage au niveau maximum, par le client Blizzard et donc sous ses
 * deux garde-fous de quota. Son résultat est gardé quinze minutes, jamais au-delà de la
 * prochaine remise à zéro.
 *
 * @phpstan-import-type WeeklyActivity from WeeklyActivityBuilder
 * @phpstan-import-type WeeklyCharacterInput from WeeklyActivityBuilder
 *
 * @phpstan-type WeeklyCharacterReference array{name: string, realm: string, realmSlug: string, level: int, classId: int}
 * @phpstan-type AccountWeek array{characters: WeeklyActivity['characters'], belowMaxLevel: int, maxLevel: int, updatedAt: string|null}
 * @phpstan-type AccountWeekStatus array{status: 'unauthenticated'}|array{status: 'failed'}|array{status: 'computing', jobId: string}|array{status: 'ready', data: AccountWeek}
 */
class WeeklyActivityService
{
    private const string RESULT_KEY = 'weekly_activity:'; // @pest-mutate-ignore

    private const string PENDING_KEY = 'weekly_activity:pending:'; // @pest-mutate-ignore

    private const string JOB_KEY = 'weekly_activity_job:'; // @pest-mutate-ignore

    private const int JOB_TTL_S = 3600; // @pest-mutate-ignore

    private const int FRESH_FOR_S = 900; // @pest-mutate-ignore

    private const int NOT_FOUND = 404; // @pest-mutate-ignore

    private const string FAILED_JOB = 'failed'; // @pest-mutate-ignore

    private const array RUNNING_JOB = ['pending', 'running']; // @pest-mutate-ignore

    public function __construct(
        private readonly UserCharacterService $userCharacterService,
        private readonly BlizzardApiClient $blizzardApiClient,
        private readonly RaidJournal $raidJournal,
        private readonly WeeklyActivityBuilder $weeklyActivityBuilder,
        private readonly WeeklyReset $weeklyReset,
    ) {}

    /**
     * @return AccountWeekStatus
     *
     * @throws MissingBattleTagException
     */
    public function getOrCompute(string $battleTag): array
    {
        $bnetUserId = $this->bnetUserId();

        if (! $this->userCharacterService->isAuthenticated() || $bnetUserId === '') {
            return ['status' => 'unauthenticated'];
        }

        /** @var AccountWeek|null $stored */
        $stored = Cache::get($this->resultKey($bnetUserId));

        if ($stored !== null) {
            return ['status' => 'ready', 'data' => $stored];
        }

        $pendingJobId = Cache::get($this->pendingKey($bnetUserId));

        if (is_string($pendingJobId)) {
            return $this->followJob($bnetUserId, $pendingJobId, $battleTag);
        }

        return $this->queue($bnetUserId, $battleTag);
    }

    /**
     * Le prochain appel recalcule la semaine.
     */
    public function refresh(): void
    {
        $bnetUserId = $this->bnetUserId();

        Cache::forget($this->resultKey($bnetUserId));
        Cache::forget($this->pendingKey($bnetUserId));
    }

    /**
     * Le travail du job : lit Blizzard, assemble la semaine et la garde pour le compte.
     *
     * @param  list<WeeklyCharacterReference>  $characters
     */
    public function computeAndStore(string $bnetUserId, array $characters, DateTimeImmutable $now): void
    {
        $maxLevel = max([0, ...array_column($characters, 'level')]);
        $inputs = array_map(fn (array $character): array => $this->input($character, $character['level'] === $maxLevel), $characters);

        $week = $this->weeklyActivityBuilder->build($inputs, $this->raidJournal->forInstances($this->seasonRaids($inputs)), $this->seasonDungeons(), $now);

        Cache::put($this->resultKey($bnetUserId), [...$week, 'updatedAt' => $now->format('Y-m-d\TH:i:s\Z')], $this->freshFor($now));
    }

    public function markJob(string $jobId, string $status): void
    {
        Cache::put(self::JOB_KEY.$jobId, ['status' => $status], self::JOB_TTL_S);
    }

    /**
     * Un job abouti a gardé la semaine : si elle n'y est plus, un nouveau job la recalcule. Un job
     * en échec est signalé, et le suivant en relance un.
     *
     * @return array{status: 'failed'}|array{status: 'computing', jobId: string}|array{status: 'ready', data: AccountWeek}
     *
     * @throws MissingBattleTagException
     */
    private function followJob(string $bnetUserId, string $jobId, string $battleTag): array
    {
        /** @var array{status: string} $job */
        $job = Cache::get(self::JOB_KEY.$jobId, ['status' => self::FAILED_JOB]);

        if (in_array($job['status'], self::RUNNING_JOB, true)) {
            return ['status' => 'computing', 'jobId' => $jobId];
        }

        Cache::forget($this->pendingKey($bnetUserId));

        if ($job['status'] === self::FAILED_JOB) {
            return ['status' => 'failed'];
        }

        return $this->queue($bnetUserId, $battleTag);
    }

    /**
     * @return array{status: 'computing', jobId: string}|array{status: 'ready', data: AccountWeek}
     *
     * @throws MissingBattleTagException
     */
    private function queue(string $bnetUserId, string $battleTag): array
    {
        $characters = $this->userCharacterService->getUserCharacters();

        if ($characters === []) {
            return ['status' => 'ready', 'data' => ['characters' => [], 'belowMaxLevel' => 0, 'maxLevel' => 0, 'updatedAt' => null]];
        }

        throw_if($battleTag === '', MissingBattleTagException::forAccount($bnetUserId));

        $jobId = Str::uuid()->toString();
        $this->markJob($jobId, 'pending');
        Cache::put($this->pendingKey($bnetUserId), $jobId, self::JOB_TTL_S);

        // Only what the computation reads goes into the queue.
        $references = array_map(static fn (array $character): array => [
            'name' => $character['name'],
            'realm' => $character['realm'],
            'realmSlug' => $character['realmSlug'],
            'level' => $character['level'],
            'classId' => $character['classId'],
        ], $characters);

        dispatch(new ComputeWeeklyActivityJob($jobId, $bnetUserId, $references, $battleTag, AppLocale::current()->value));

        return ['status' => 'computing', 'jobId' => $jobId];
    }

    /**
     * Seuls les personnages au niveau maximum coûtent des appels : les autres sont comptés sans être lus.
     *
     * @param  WeeklyCharacterReference  $character
     * @return WeeklyCharacterInput
     */
    private function input(array $character, bool $atMaxLevel): array
    {
        $base = sprintf('profile/wow/character/%s/%s', mb_strtolower($character['realmSlug']), mb_strtolower($character['name']));
        $raids = $atMaxLevel ? $this->profile($base.'/encounters/raids') : null;
        $mythicKeystone = $atMaxLevel ? $this->profile($base.'/mythic-keystone-profile') : null;

        return [
            ...$character,
            'raids' => $raids instanceof ResponsePayload ? CharacterRaidsResponse::fromPayload($raids) : null,
            'mythicKeystone' => $mythicKeystone instanceof ResponsePayload ? MythicKeystoneProfileResponse::fromPayload($mythicKeystone) : null,
        ];
    }

    /**
     * Un personnage que Blizzard ne connaît plus répond 404 : il reste à zéro. Toute autre erreur
     * fait échouer le job.
     */
    private function profile(string $endpoint): ?ResponsePayload
    {
        try {
            return $this->blizzardApiClient->getResponse($endpoint);
        } catch (ResponseException $responseException) {
            throw_unless($responseException->getResponse()->getStatusCode() === self::NOT_FOUND, $responseException);

            return null;
        }
    }

    /**
     * Les raids de la saison sont ceux où au moins un personnage du compte est entré : Blizzard
     * ne les liste pas ailleurs.
     *
     * @param  list<WeeklyCharacterInput>  $inputs
     * @return list<int>
     */
    private function seasonRaids(array $inputs): array
    {
        $instanceIds = array_map(
            static fn (array $input): array => $input['raids']?->instanceIdsOf(RaidProgressAggregator::CURRENT_SEASON_EXPANSION_ID) ?? [],
            $inputs,
        );

        return array_values(array_unique(array_merge(...$instanceIds)));
    }

    /**
     * @return array<int, string>
     */
    private function seasonDungeons(): array
    {
        $dungeons = [];
        foreach ($this->blizzardApiClient->getCurrentMythicDungeons() as $mythicDungeon) {
            $dungeons[$mythicDungeon->id] = $mythicDungeon->name;
        }

        return $dungeons;
    }

    private function freshFor(DateTimeImmutable $now): int
    {
        $untilReset = $this->weeklyReset->nextResetAt($now)->getTimestamp() - $now->getTimestamp();

        return min(self::FRESH_FOR_S, $untilReset);
    }

    private function bnetUserId(): string
    {
        /** @var string $bnetUserId */
        $bnetUserId = Session::get('bnet_user_id', '');

        return $bnetUserId;
    }

    /** The week names raids and dungeons as Blizzard does, in the language of the page. */
    private function resultKey(string $bnetUserId): string
    {
        return AppLocale::current()->scopedCacheKey(self::RESULT_KEY.$bnetUserId);
    }

    private function pendingKey(string $bnetUserId): string
    {
        return AppLocale::current()->scopedCacheKey(self::PENDING_KEY.$bnetUserId);
    }
}
