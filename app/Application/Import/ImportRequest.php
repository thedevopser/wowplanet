<?php

declare(strict_types=1);

namespace App\Application\Import;

/**
 * Un ordre en attente d'être lu par l'import : lequel, de qui, et depuis quand.
 *
 * Le demandeur voyage avec l'ordre parce que c'est le job qui journalise, à la frontière
 * de tranche suivante, et non le contrôleur qui a reçu le clic : sans lui, le journal
 * dirait ce qui s'est passé sans pouvoir dire qui l'a voulu.
 */
final readonly class ImportRequest
{
    /**
     * Une pause oubliée finit par relâcher le verrou « un import à la fois », sans quoi
     * un aller-retour distrait confisquerait le panneau jusqu'à l'expiration du pointeur,
     * soit une journée entière.
     */
    public const ABANDON_AFTER_S = 3600;

    public function __construct(
        public ImportSignal $signal,
        public string $actor,
        public int $requestedAt,
    ) {}

    public function waitedSeconds(int $now): int
    {
        return max(0, $now - $this->requestedAt);
    }

    /**
     * Une annulation ne s'abandonne pas : elle est agie à la frontière suivante, donc
     * elle ne reste jamais en attente assez longtemps pour que la question se pose.
     */
    public function isAbandoned(int $now): bool
    {
        return $this->signal === ImportSignal::Pause
            && $this->waitedSeconds($now) > self::ABANDON_AFTER_S;
    }

    /**
     * @return array{signal: string, actor: string, requested_at: int}
     */
    public function toArray(): array
    {
        return [
            'signal' => $this->signal->value,
            'actor' => $this->actor,
            'requested_at' => $this->requestedAt,
        ];
    }

    /**
     * Un ordre qu'on ne sait pas nommer se lit comme aucun ordre : la pire réaction à une
     * clé abîmée serait de laisser un import en pause sans moyen de le reprendre.
     *
     * @param  array{signal?: string, actor?: string, requested_at?: int}  $payload
     */
    public static function fromArray(array $payload): ?self
    {
        $signal = ImportSignal::tryFrom($payload['signal'] ?? '');

        if (! $signal instanceof ImportSignal) {
            return null;
        }

        return new self($signal, $payload['actor'] ?? '', $payload['requested_at'] ?? 0);
    }
}
