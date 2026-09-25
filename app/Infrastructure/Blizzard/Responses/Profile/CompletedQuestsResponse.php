<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Profile;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * Les quêtes terminées d'un personnage, `…/quests/completed`.
 */
final readonly class CompletedQuestsResponse
{
    /**
     * @param  list<int>  $questIds
     */
    public function __construct(public array $questIds) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        $questIds = [];
        foreach ($responsePayload->objectList('quests') as $quest) {
            $questId = $quest->optionalInt('id');
            if ($questId !== null) {
                $questIds[] = $questId;
            }
        }

        return new self($questIds);
    }
}
