<?php

declare(strict_types=1);

namespace App\Application\Import;

/**
 * L'état d'un import entier, celui que le panneau lit pour savoir quoi proposer.
 *
 * Il se déduit des étapes, sauf quand l'administrateur a repris la main : `Paused` et
 * `Cancelled` ne sont atteignables par aucun enchaînement d'étapes, et c'est ce qui les
 * distingue. Les étapes, elles, gardent leur propre statut — l'étape abandonnée en cours
 * d'annulation reste « en cours » dans le rapport, ce qu'elle était réellement.
 */
enum ImportRunState: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Paused = 'paused';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    /**
     * Une pause n'est pas terminale : c'est précisément ce qui fait que le panneau
     * continue de suivre l'import et peut en proposer la reprise.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Cancelled], true);
    }

    public function isInterruption(): bool
    {
        return in_array($this, [self::Paused, self::Cancelled], true);
    }
}
