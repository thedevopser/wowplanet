<?php

declare(strict_types=1);

use App\Application\Import\ImportStage;
use App\Models\WowAchievement;
use App\Models\WowAppearance;
use App\Models\WowDecor;
use App\Models\WowMount;
use App\Models\WowPet;
use App\Models\WowProfession;
use App\Models\WowQuest;
use App\Models\WowRecipe;

test('the chain starts with the reference socle', function (): void {
    expect(ImportStage::chain()[0])->toBe(ImportStage::Reference);
});

test('the chain holds every stage exactly once', function (): void {
    expect(ImportStage::chain())->toEqualCanonicalizing(ImportStage::cases());
});

test('every stage comes after the stages it depends on', function (): void {
    $seen = [];

    foreach (ImportStage::chain() as $importStage) {
        foreach ($importStage->dependsOn() as $dependency) {
            expect($seen)->toContain($dependency);
        }

        $seen[] = $importStage;
    }
});

test('the stages fed by the reference socle declare it', function (): void {
    expect(ImportStage::Quests->dependsOn())->toBe([ImportStage::Reference])
        ->and(ImportStage::Mounts->dependsOn())->toBe([ImportStage::Reference])
        ->and(ImportStage::Professions->dependsOn())->toBe([ImportStage::Reference])
        ->and(ImportStage::Achievements->dependsOn())->toBe([]);
});

test('a stage names the tables its rows are counted in', function (ImportStage $importStage, array $tables): void {
    expect($importStage->tables())->toBe($tables);
})->with([
    'achievements' => [ImportStage::Achievements, [WowAchievement::class]],
    'quests' => [ImportStage::Quests, [WowQuest::class]],
    'professions' => [ImportStage::Professions, [WowProfession::class, WowRecipe::class]],
    'mounts' => [ImportStage::Mounts, [WowMount::class]],
    'pets' => [ImportStage::Pets, [WowPet::class]],
    'decor' => [ImportStage::Decor, [WowDecor::class]],
    'appearances' => [ImportStage::Appearances, [WowAppearance::class]],
]);

test('the reference socle counts no rows, its tables carrying no timestamps', function (): void {
    expect(ImportStage::Reference->tables())->toBe([]);
});

test('only the wardrobe sweep resumes from an offset', function (): void {
    $resumable = array_values(array_filter(
        ImportStage::cases(),
        static fn (ImportStage $importStage): bool => $importStage->isResumable(),
    ));

    expect($resumable)->toBe([ImportStage::Appearances]);
});

test('requesting everything runs the whole chain', function (): void {
    expect(ImportStage::requested('all'))->toBe(ImportStage::chain());
});

test('requesting one stage runs that stage alone', function (): void {
    expect(ImportStage::requested('quests'))->toBe([ImportStage::Quests]);
});

test('requesting an unknown stage runs nothing', function (): void {
    expect(ImportStage::requested('dragons'))->toBe([]);
});

test('every stage carries a label for the report', function (): void {
    foreach (ImportStage::cases() as $stage) {
        expect($stage->label())->not->toBe('');
    }
});

test('every stage but the reference socle spends the Blizzard quota', function (): void {
    $offline = array_values(array_filter(
        ImportStage::cases(),
        static fn (ImportStage $importStage): bool => ! $importStage->usesBlizzardApi(),
    ));

    expect($offline)->toBe([ImportStage::Reference]);
});

// ─── sélection et inventaire du panneau ─────────────────────

test('a selection of several stages is requested at once', function (): void {
    expect(ImportStage::requested('quests,mounts'))->toBe([ImportStage::Quests, ImportStage::Mounts]);
});

test('a selection is rendered in the order of the chain, not in the order it was typed', function (): void {
    expect(ImportStage::requested('appearances,achievements'))->toBe([ImportStage::Achievements, ImportStage::Appearances]);
});

test('a stage named twice in a selection runs once', function (): void {
    expect(ImportStage::requested('mounts,mounts'))->toBe([ImportStage::Mounts]);
});

test('a selection ignores the blanks around the names', function (): void {
    expect(ImportStage::requested(' quests , mounts '))->toBe([ImportStage::Quests, ImportStage::Mounts]);
});

test('an unknown name in a selection makes the whole selection invalid', function (): void {
    expect(ImportStage::requested('quests,dragons'))->toBe([]);
});

test('the catalogue leaves the reference socle out, since it is not an entity to browse', function (): void {
    expect(ImportStage::catalogue())->toBe([
        ImportStage::Achievements,
        ImportStage::Quests,
        ImportStage::Professions,
        ImportStage::Mounts,
        ImportStage::Pets,
        ImportStage::Decor,
        ImportStage::Appearances,
    ]);
});

test('every catalogue stage declares an order of magnitude of API calls', function (): void {
    foreach (ImportStage::catalogue() as $importStage) {
        expect($importStage->estimatedApiCalls())->toBeGreaterThan(0);
    }
});

test('the reference socle costs nothing on the Blizzard quota, and says so', function (): void {
    expect(ImportStage::Reference->estimatedApiCalls())->toBe(0);
});

test('the wardrobe is not the heaviest stage, contrary to what its duration suggests', function (): void {
    expect(ImportStage::Appearances->estimatedApiCalls())
        ->toBeLessThan(ImportStage::Achievements->estimatedApiCalls());
});
