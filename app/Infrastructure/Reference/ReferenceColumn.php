<?php

declare(strict_types=1);

namespace App\Infrastructure\Reference;

final readonly class ReferenceColumn
{
    public function __construct(
        public string $source,
        public string $target,
        public ReferenceColumnType $type,
    ) {}
}
