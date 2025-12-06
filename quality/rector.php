<?php

declare(strict_types=1);

use Rector\TypeDeclaration\Rector\StmtsAwareInterface\DeclareStrictTypesRector;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Symfony\Set\SymfonySetList;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/../src',
        __DIR__ . '/../tests',
    ]);

    $rectorConfig->skip([
        __DIR__ . '/../vendor',
        __DIR__ . '/../var',
        __DIR__ . '../tests/Fixtures',
        // Désactivation de la promotion des propriétés dans le constructeur
        ClassPropertyAssignToConstructorPromotionRector::class,
    ]);

    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_84,
        SymfonySetList::SYMFONY_80,
    ]);

    $rectorConfig->rule(DeclareStrictTypesRector::class);
};
