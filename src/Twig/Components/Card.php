<?php

declare(strict_types=1);

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Card
{
    public string $icon = '';
    public string $title = '';
    public string $description = '';
    public ?string $buttonText = null;
    public ?string $buttonUrl = null;
    public bool $disabled = false;
    public bool $hover = true;
}
