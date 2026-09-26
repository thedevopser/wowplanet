<?php

declare(strict_types=1);

use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Models\WowCollectionTaxonomy;
use App\Models\WowMount;
use App\Models\WowPet;
use Inertia\Testing\AssertableInertia as Assert;

function mountNamed(int $id, string $name, ?string $source = null): WowMount
{
    return WowMount::query()->create([
        'id' => $id,
        'name_fr' => $name,
        'source' => $source,
        'is_active' => true,
    ]);
}

function ranked(CollectionEntity $collectionEntity, int $entryId, ?string $category, ?string $source): void
{
    WowCollectionTaxonomy::query()->insert([
        'entity' => $collectionEntity->value,
        'entry_id' => $entryId,
        'category' => $category,
        'source' => $source,
        'obtainable' => true,
    ]);
}

test('the taxonomy page renders AdminTaxonomyPage for an administrator', function (): void {
    $this->withSession(['is_admin' => true])
        ->get('/admin/taxonomy')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert->component('AdminTaxonomyPage'));
});

test('the taxonomy page is closed to anyone else', function (): void {
    $this->get('/admin/taxonomy')->assertRedirect('/');
});

test('it opens on the mounts, and says what each collection has left to arbitrate', function (): void {
    mountNamed(7, 'Loup gris');
    WowPet::query()->create(['id' => 69, 'name_fr' => 'Chouette blanche', 'is_active' => true]);

    $this->withSession(['is_admin' => true])
        ->get('/admin/taxonomy')
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('entity', 'mount')
            ->where('counts.mount.pending', 1)
            ->where('counts.pet.pending', 1)
            ->where('counts.decor.pending', 0)
            ->etc());
});

test('it lists the pending entries of the collection asked for', function (): void {
    mountNamed(7, 'Loup gris', 'Trading Post');

    $this->withSession(['is_admin' => true])
        ->get('/admin/taxonomy?entity=mount')
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('entries.0.id', 7)
            ->where('entries.0.name', 'Loup gris')
            ->where('entries.0.pending_source', 'Trading Post')
            ->etc());
});

test('an entity the server does not know falls back to the mounts rather than failing', function (): void {
    $this->withSession(['is_admin' => true])
        ->get('/admin/taxonomy?entity=wow_mounts;DROP')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert->where('entity', 'mount')->etc());
});

test('a search narrows the list and says how many it matched', function (): void {
    mountNamed(7, 'Loup gris');
    mountNamed(12, 'Étalon blanc');

    $this->withSession(['is_admin' => true])
        ->get('/admin/taxonomy?search=loup')
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('search', 'loup')
            ->where('matched', 1)
            ->has('entries', 1)
            ->etc());
});

/**
 * Sept cents mascottes en attente ne se parcourent pas d'un bloc : la page en sert une
 * tranche, et annonce ce qu'elle a laissé de côté.
 */
test('a long list is served one page at a time, and the total is still told', function (): void {
    for ($id = 1; $id <= 55; $id++) {
        mountNamed($id, 'Monture '.$id);
    }

    $this->withSession(['is_admin' => true])
        ->get('/admin/taxonomy')
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->has('entries', 50)
            ->where('matched', 55)
            ->where('perPage', 50)
            ->etc());
});

test('the page offers the vocabulary the collection really uses', function (): void {
    ranked(CollectionEntity::Mount, 1, 'Racial', 'Human');
    ranked(CollectionEntity::Pet, 2, 'Classic', 'Vendor');

    $this->withSession(['is_admin' => true])
        ->get('/admin/taxonomy')
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('vocabulary.categories', ['Racial'])
            ->where('vocabulary.sources', ['Human'])
            ->etc());
});

test('the page carries the state of the versioned snapshot, since an arbitration makes it drift', function (): void {
    ranked(CollectionEntity::Mount, 1, 'Racial', 'Human');

    $this->withSession(['is_admin' => true])
        ->get('/admin/taxonomy')
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->has('snapshot.path')
            ->has('snapshot.entries')
            ->has('snapshot.in_step')
            ->etc());
});

test('the dashboard carries the count, which is the only way not to forget it', function (): void {
    mountNamed(7, 'Loup gris');

    $this->withSession(['is_admin' => true])
        ->get('/admin')
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('pendingTaxonomy.mount.pending', 1)
            ->etc());
});

test('the page opens on the pending entries, and tells how many entries each collection already ranks', function (): void {
    mountNamed(7, 'Loup gris');
    mountNamed(8, 'Étalon blanc');
    ranked(CollectionEntity::Mount, 8, 'Racial', 'Human');

    $this->withSession(['is_admin' => true])
        ->get('/admin/taxonomy')
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('mode', 'pending')
            ->where('curatedCounts', ['mount' => 1, 'pet' => 0, 'decor' => 0])
            ->where('entries.0.id', 7)
            ->has('entries', 1)
            ->missing('categories')
            ->etc());
});

test('in curated mode, it lists the ranked entries with their current ranking', function (): void {
    mountNamed(7, 'Loup gris');
    mountNamed(8, 'Étalon blanc');
    ranked(CollectionEntity::Mount, 8, 'Racial', 'Human');

    $this->withSession(['is_admin' => true])
        ->get('/admin/taxonomy?mode=curated')
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('mode', 'curated')
            ->where('entries', [['id' => 8, 'name' => 'Étalon blanc', 'category' => 'Racial', 'source' => 'Human']])
            ->where('matched', 1)
            ->where('counts.mount.pending', 1)
            ->where('curatedCounts.mount', 1)
            ->where('categories', [['value' => 'Racial', 'category' => 'Racial', 'entries' => 1]])
            ->where('category', null)
            ->etc());
});

test('in curated mode, a category filter opens what sits under it', function (): void {
    mountNamed(7, 'Loup gris');
    mountNamed(8, 'Étalon blanc');
    ranked(CollectionEntity::Mount, 7, 'Other', null);
    ranked(CollectionEntity::Mount, 8, 'Legion', null);

    $this->withSession(['is_admin' => true])
        ->get('/admin/taxonomy?mode=curated&category=Other')
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('category', 'Other')
            ->where('matched', 1)
            ->where('entries.0.id', 7)
            ->etc());
});

test('in curated mode, the uncategorised filter opens the entries ranked nowhere', function (): void {
    mountNamed(7, 'Loup gris');
    mountNamed(8, 'Étalon blanc');
    ranked(CollectionEntity::Mount, 7, null, null);
    ranked(CollectionEntity::Mount, 8, 'Legion', null);

    $this->withSession(['is_admin' => true])
        ->get('/admin/taxonomy?mode=curated&category=__none__')
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('category', '__none__')
            ->where('entries.0.id', 7)
            ->has('entries', 1)
            ->etc());
});

test('in curated mode, the search works as it does on the pending entries', function (): void {
    mountNamed(7, 'Loup gris');
    mountNamed(8, 'Étalon blanc');
    ranked(CollectionEntity::Mount, 7, 'Other', null);
    ranked(CollectionEntity::Mount, 8, 'Other', null);

    $this->withSession(['is_admin' => true])
        ->get('/admin/taxonomy?mode=curated&search=loup')
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('search', 'loup')
            ->where('matched', 1)
            ->where('entries.0.id', 7)
            ->etc());
});

test('in curated mode, a long list is served one page at a time', function (): void {
    for ($id = 1; $id <= 55; $id++) {
        mountNamed($id, sprintf('Monture %02d', $id));
        ranked(CollectionEntity::Mount, $id, 'Other', null);
    }

    $this->withSession(['is_admin' => true])
        ->get('/admin/taxonomy?mode=curated')
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->has('entries', 50)
            ->where('matched', 55)
            ->etc());
});

test('a mode the server does not know falls back to the pending entries', function (): void {
    $this->withSession(['is_admin' => true])
        ->get('/admin/taxonomy?mode=everything')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert->where('mode', 'pending')->etc());
});

test('a blank category filter is no filter at all', function (): void {
    mountNamed(7, 'Loup gris');
    ranked(CollectionEntity::Mount, 7, 'Other', null);

    $this->withSession(['is_admin' => true])
        ->get('/admin/taxonomy?mode=curated&category=%20')
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('category', null)
            ->has('entries', 1)
            ->etc());
});
