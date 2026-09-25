<?php

declare(strict_types=1);

use App\Application\DTOs\AppearanceImportProgress;
use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\BlizzardBatchImporter;
use App\Infrastructure\Blizzard\ImportBuildGate;
use App\Infrastructure\Reference\FactionReference;
use App\Infrastructure\Reference\ReferenceMaps;
use App\Models\WowImportState;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    seedCollectionTaxonomy();
    Sleep::fake();

    $this->importerMock = $this->mock(BlizzardBatchImporter::class);
    $this->referenceMapsMock = $this->mock(ReferenceMaps::class);
    $this->factionReferenceMock = $this->mock(FactionReference::class);
    $this->apiClientMock = $this->mock(BlizzardApiClient::class);

    $this->apiClientMock->shouldReceive('currentBuild')->andReturn('12.1.0_68914')->byDefault();

    $this->referenceMapsMock->shouldReceive('questExpansions')->andReturn([])->byDefault();
    $this->referenceMapsMock->shouldReceive('questFactions')->andReturn([])->byDefault();
    $this->referenceMapsMock->shouldReceive('zoneFactions')->andReturn([])->byDefault();
    $this->referenceMapsMock->shouldReceive('recipeFactions')->andReturn([])->byDefault();
    $this->factionReferenceMock->shouldReceive('factions')->andReturn([])->byDefault();
});

/**
 * Le socle se synchronise depuis wago, hors de portée d'un test de commande : le tenir
 * pour déjà chargé sur le build que wago publie laisse la chaîne dérouler les sept entités.
 * Sa fraîcheur se juge sur ce build et sur l'inventaire des téléchargements, pas sur le
 * build Blizzard.
 */
function referenceAlreadyLoaded(): void
{
    stubWagoBuild('12.1.0.69875');
    referenceLoadedOn('12.1.0.69875');
}

test('command imports all types by default', function (): void {
    referenceAlreadyLoaded();

    $this->importerMock->shouldReceive('importAchievements')->once();
    $this->importerMock->shouldReceive('importQuests')->once();
    $this->importerMock->shouldReceive('tagMirrorQuestFactions')->once();
    $this->importerMock->shouldReceive('importMounts')->once();
    $this->importerMock->shouldReceive('importPets')->once();
    $this->importerMock->shouldReceive('importProfessions')->once();
    $this->importerMock->shouldReceive('tagMirrorRecipeFactions')->once();
    $this->importerMock->shouldReceive('importDecor')->once();
    $this->importerMock->shouldReceive('importAppearanceChunk')
        ->once()
        ->andReturn(new AppearanceImportProgress(done: true, offset: 0, total: 0, secondsUntilBudget: 0));

    $this->artisan('app:wow-data-import')->assertSuccessful();
});

test('command imports only quests when --type=quests', function (): void {
    $this->importerMock->shouldReceive('importQuests')->once();
    $this->importerMock->shouldReceive('tagMirrorQuestFactions')->once();
    $this->importerMock->shouldNotReceive('importAchievements');
    $this->importerMock->shouldNotReceive('importMounts');

    $this->artisan('app:wow-data-import', ['--type' => 'quests'])->assertSuccessful();
});

test('command imports only achievements when --type=achievements', function (): void {
    $this->importerMock->shouldReceive('importAchievements')->once();
    $this->importerMock->shouldNotReceive('importQuests');

    $this->artisan('app:wow-data-import', ['--type' => 'achievements'])->assertSuccessful();
});

test('command imports only mounts when --type=mounts', function (): void {
    $this->importerMock->shouldReceive('importMounts')->once();
    $this->importerMock->shouldNotReceive('importQuests');
    $this->importerMock->shouldNotReceive('importAchievements');

    $this->artisan('app:wow-data-import', ['--type' => 'mounts'])->assertSuccessful();
});

test('command imports only professions when --type=professions', function (): void {
    $this->importerMock->shouldReceive('importProfessions')->once();
    $this->importerMock->shouldReceive('tagMirrorRecipeFactions')->once();
    $this->importerMock->shouldNotReceive('importQuests');

    $this->artisan('app:wow-data-import', ['--type' => 'professions'])->assertSuccessful();
});

test('an unknown type is refused rather than silently importing nothing', function (): void {
    $this->artisan('app:wow-data-import', ['--type' => 'dragons'])->assertFailed();
});

// ─── garde-robe ─────────────────────────────────────────────

test('the wardrobe sweep runs inline until its last window', function (): void {
    $this->importerMock->shouldReceive('importAppearanceChunk')
        ->twice()
        ->andReturn(
            new AppearanceImportProgress(done: false, offset: 143, total: 286, secondsUntilBudget: 0),
            new AppearanceImportProgress(done: true, offset: 286, total: 286, secondsUntilBudget: 0),
        );

    $this->artisan('app:wow-data-import', ['--type' => 'appearances'])->assertSuccessful();
});

test('a full refresh is passed down to the wardrobe sweep', function (): void {
    $this->importerMock->shouldReceive('importAppearanceChunk')
        ->once()
        ->withArgs(fn (bool $full): bool => $full)
        ->andReturn(new AppearanceImportProgress(done: true, offset: 0, total: 0, secondsUntilBudget: 0));

    $this->artisan('app:wow-data-import', ['--type' => 'appearances', '--full' => true])->assertSuccessful();
});

test('the window cap is passed down to the wardrobe sweep', function (): void {
    $this->importerMock->shouldReceive('importAppearanceChunk')
        ->once()
        ->withArgs(fn (bool $full, int $offset, int $timeBox, ?int $limit): bool => $limit === 3)
        ->andReturn(new AppearanceImportProgress(done: true, offset: 0, total: 0, secondsUntilBudget: 0));

    $this->artisan('app:wow-data-import', ['--type' => 'appearances', '--limit' => 3])->assertSuccessful();
});

test('the command waits out the hourly ceiling and says so', function (): void {
    $this->importerMock->shouldReceive('importAppearanceChunk')
        ->twice()
        ->andReturn(
            new AppearanceImportProgress(done: false, offset: 0, total: 286, secondsUntilBudget: 240),
            new AppearanceImportProgress(done: true, offset: 286, total: 286, secondsUntilBudget: 0),
        );

    $this->artisan('app:wow-data-import', ['--type' => 'appearances'])
        ->expectsOutputToContain('plafond horaire')
        ->assertSuccessful();

    Sleep::assertSlept(fn (\DateInterval $dateInterval): bool => $dateInterval->s === 240);
});

// ─── rapport de fin ─────────────────────────────────────────

test('the command reports what each stage did', function (): void {
    $this->importerMock->shouldReceive('importMounts')->once();

    $this->artisan('app:wow-data-import', ['--type' => 'mounts'])
        ->expectsOutputToContain('Montures')
        ->expectsOutputToContain('Import terminé')
        ->assertSuccessful();
});

test('a stage that fails is reported and fails the command without stopping the rest', function (): void {
    referenceAlreadyLoaded();

    $this->importerMock->shouldReceive('importAchievements')->once()->andThrow(new RuntimeException('achievement tree unavailable'));
    $this->importerMock->shouldReceive('importQuests')->once();
    $this->importerMock->shouldReceive('tagMirrorQuestFactions')->once();
    $this->importerMock->shouldReceive('importMounts')->once();
    $this->importerMock->shouldReceive('importPets')->once();
    $this->importerMock->shouldReceive('importProfessions')->once();
    $this->importerMock->shouldReceive('tagMirrorRecipeFactions')->once();
    $this->importerMock->shouldReceive('importDecor')->once();
    $this->importerMock->shouldReceive('importAppearanceChunk')
        ->once()
        ->andReturn(new AppearanceImportProgress(done: true, offset: 0, total: 0, secondsUntilBudget: 0));

    $this->artisan('app:wow-data-import')
        ->expectsOutputToContain('achievement tree unavailable')
        ->assertFailed();
});

// ─── détection de build ─────────────────────────────────────

test('it imports nothing when every requested entity already sits on the current build', function (): void {
    referenceAlreadyLoaded();
    $gate = new ImportBuildGate;
    foreach (['achievements', 'quests', 'mounts', 'pets', 'professions', 'decor', 'appearances'] as $entity) {
        $gate->remember($entity, '12.1.0_68914');
    }

    $this->importerMock->shouldNotReceive('importAchievements');
    $this->importerMock->shouldNotReceive('importQuests');
    $this->importerMock->shouldNotReceive('importMounts');

    $this->artisan('app:wow-data-import')
        ->expectsOutputToContain('déjà à jour')
        ->assertSuccessful();
});

test('it reimports an unchanged build when --force is passed', function (): void {
    (new ImportBuildGate)->remember('quests', '12.1.0_68914');

    $this->importerMock->shouldReceive('importQuests')->once();
    $this->importerMock->shouldReceive('tagMirrorQuestFactions')->once();

    $this->artisan('app:wow-data-import', ['--type' => 'quests', '--force' => true])->assertSuccessful();
});

test('it imports the entities that lag behind and leaves the others alone', function (): void {
    (new ImportBuildGate)->remember('quests', '12.1.0_68914');

    $this->importerMock->shouldNotReceive('importQuests');
    $this->importerMock->shouldReceive('importMounts')->once();

    $this->artisan('app:wow-data-import', ['--type' => 'mounts'])->assertSuccessful();
    $this->artisan('app:wow-data-import', ['--type' => 'quests'])->assertSuccessful();
});

test('it records the build against each entity it imports', function (): void {
    $this->importerMock->shouldReceive('importMounts')->once();

    $this->artisan('app:wow-data-import', ['--type' => 'mounts'])->assertSuccessful();

    expect(WowImportState::query()->where('entity', 'mounts')->value('build'))->toBe('12.1.0_68914');
});

test('it imports as usual when the build cannot be determined', function (): void {
    $this->apiClientMock->shouldReceive('currentBuild')->andReturn(null);

    $this->importerMock->shouldReceive('importMounts')->once();

    $this->artisan('app:wow-data-import', ['--type' => 'mounts'])->assertSuccessful();

    expect(WowImportState::query()->where('entity', 'mounts')->exists())->toBeFalse();
});
