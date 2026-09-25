<?php

declare(strict_types=1);

namespace App\Application\Health;

use App\Infrastructure\Blizzard\HourlyBudgetGuard;

/**
 * Tout ce que la page de santé affiche, mesuré section par section.
 *
 * Chaque section est calculée sous garde : une base injoignable rend les volumétries
 * indisponibles, pas la page. C'est ce qui permet au diagnostic de fonctionner
 * justement quand quelque chose est en panne.
 *
 * @phpstan-import-type ListedJob from QueueState
 */
final readonly class HealthReport
{
    public const RECENT_ERRORS = 50;

    public function __construct(
        private ServiceProbes $serviceProbes,
        private HourlyBudgetGuard $hourlyBudgetGuard,
        private QueueState $queueState,
        private CatalogueVolumetry $catalogueVolumetry,
        private ApplicationErrorLog $applicationErrorLog,
    ) {}

    /**
     * @return array{
     *     services: list<array{service: string, status: string, issue: string|null, detail: string|null}>,
     *     quota: array{status: string, issue: string|null, detail?: string, used?: int, import_ceiling?: int, enforced_limit?: int, published_quota?: int},
     *     queue: array{status: string, issue: string|null, detail?: string, queue?: string, pending?: int, delayed?: int, reserved?: int, current?: array{job_id: string, started_at: int}|null, running?: list<ListedJob>, waiting?: list<ListedJob>, failed?: list<array{uuid: string, queue: string, job: string, exception: string, failed_at: string}>},
     *     volumes: array{status: string, issue: string|null, detail?: string, tables?: list<array{table: string, family: string, rows: int, active: int|null, without_icon: int|null, status: string, issue: string|null}>},
     *     errors: array{status: string, issue: string|null, detail?: string, entries?: list<array{id: int, level: string, message: string, exception_class: string|null, location: string|null, occurred_at: string}>}
     * }
     */
    public function snapshot(): array
    {
        return [
            'services' => $this->serviceProbes->probe(),
            'quota' => $this->guard('Quota Blizzard', fn (): array => $this->quota()->toArray()),
            'queue' => $this->queue(),
            'volumes' => $this->guard('Volumétries', $this->volumes(...)),
            'errors' => $this->guard('Erreurs récentes', fn (): array => [
                'status' => HealthStatus::Ok->value,
                'issue' => null,
                'entries' => $this->applicationErrorLog->latest(self::RECENT_ERRORS),
            ]),
        ];
    }

    /**
     * La section Queue seule, sous la même garde que dans la page : le suivi en direct la
     * remesure sans refaire les volumétries ni les sondes.
     *
     * @return array{status: string, issue: string|null, detail?: string, queue?: string, pending?: int, delayed?: int, reserved?: int, current?: array{job_id: string, started_at: int}|null, running?: list<ListedJob>, waiting?: list<ListedJob>, failed?: list<array{uuid: string, queue: string, job: string, exception: string, failed_at: string}>}
     */
    public function queue(): array
    {
        return $this->guard('Queue', $this->queueState->snapshot(...));
    }

    private function quota(): BlizzardQuota
    {
        $ceiling = config('services.blizzard.import_hourly_ceiling');
        throw_unless(is_int($ceiling), \UnexpectedValueException::class, 'The import hourly ceiling is not an integer.');

        return new BlizzardQuota($this->hourlyBudgetGuard->usedInWindow(), $ceiling);
    }

    /**
     * @return array{status: string, issue: string|null, tables: list<array{table: string, family: string, rows: int, active: int|null, without_icon: int|null, status: string, issue: string|null}>}
     */
    private function volumes(): array
    {
        $volumes = $this->catalogueVolumetry->volumes();
        $empty = count(array_filter($volumes, static fn (TableVolume $tableVolume): bool => $tableVolume->status() === HealthStatus::Critical));

        return [
            'status' => ($empty === 0 ? HealthStatus::Ok : HealthStatus::Critical)->value,
            'issue' => match (true) {
                $empty === 0 => null,
                $empty === 1 => '1 table du catalogue vide.',
                default => sprintf('%d tables du catalogue vides.', $empty),
            },
            'tables' => array_map(static fn (TableVolume $tableVolume): array => $tableVolume->toArray(), $volumes),
        ];
    }

    /**
     * @template TSection of array{status: string, issue: string|null}
     *
     * @param  \Closure(): TSection  $measure
     * @return TSection|array{status: string, issue: string, detail: string}
     */
    private function guard(string $section, \Closure $measure): array
    {
        try {
            return $measure();
        } catch (\Throwable $throwable) {
            return [
                'status' => HealthStatus::Unavailable->value,
                'issue' => $section.' : mesure impossible.',
                'detail' => $throwable->getMessage(),
            ];
        }
    }
}
