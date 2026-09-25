<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Profile;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * La couleur que Blizzard associe à une cote Mythique+, transmise telle quelle au front.
 */
final readonly class RatingColor
{
    public function __construct(
        public int $r,
        public int $g,
        public int $b,
        public float $a,
    ) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        return new self(
            r: $responsePayload->requiredInt('r'),
            g: $responsePayload->requiredInt('g'),
            b: $responsePayload->requiredInt('b'),
            a: $responsePayload->requiredFloat('a'),
        );
    }

    /**
     * Lit la couleur d'un objet de cote (`mythic_rating`, `map_rating`), qui peut en être dépourvu.
     */
    public static function fromRating(?ResponsePayload $responsePayload): ?self
    {
        $color = $responsePayload?->optionalObject('color');

        return $color instanceof ResponsePayload ? self::fromPayload($color) : null;
    }

    /**
     * @return array{r: int, g: int, b: int, a: float}
     */
    public function toArray(): array
    {
        return ['r' => $this->r, 'g' => $this->g, 'b' => $this->b, 'a' => $this->a];
    }
}
