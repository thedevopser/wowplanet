<?php

declare(strict_types=1);

namespace App\Application\Services\Progress;

use App\Models\WowAppearance;
use App\Models\WowDecor;
use App\Models\WowMount;
use App\Models\WowPet;

/**
 * @phpstan-type CollectibleProgress array{id: int, name: string, is_completed: bool, source: string|null, category: string|null, wowhead_id: int|null, icon_url: string|null}
 * @phpstan-type DecorProgress array{id: int, name: string, is_completed: bool, item_id: int|null, icon_url: string|null, category: string|null, source: string|null}
 * @phpstan-type AppearanceProgress array{slot: string, category: string|null, total: int, completed: int}
 */
class CollectionProgressAggregator
{
    /**
     * Catégorie de garde-robe par slot. Volontairement figée ici plutôt que lue dans
     * wow_appearances.category : cette colonne est le item_class.name brut de Blizzard
     * (classe d'objet, non normalisée), qui contient des valeurs parasites — « Quête »,
     * « Artisanat », « Divers » — pesant 1 à 4 objets par slot.
     *
     * @var array<string, string>
     *
     * @pest-mutate-ignore
     */
    private const SLOT_CATEGORIES = [
        'HEAD' => 'Armure',
        'SHOULDER' => 'Armure',
        'SHIRT' => 'Armure',
        'CHEST' => 'Armure',
        'WAIST' => 'Armure',
        'LEGS' => 'Armure',
        'FEET' => 'Armure',
        'WRIST' => 'Armure',
        'HAND' => 'Armure',
        'CLOAK' => 'Armure',
        'TABARD' => 'Armure',
        'SHIELD' => 'Armure',
        'HOLDABLE' => 'Armure',
        'WEAPON' => 'Arme',
        'RANGED' => 'Arme',
        'TWOHWEAPON' => 'Arme',
        'WEAPONOFFHAND' => 'Arme',
    ];

    /**
     * @param  list<int>  $characterMountIds
     * @return list<CollectibleProgress>
     */
    public function aggregateMounts(array $characterMountIds): array
    {
        $result = [];
        foreach (WowMount::all() as $mount) {
            $result[] = [
                'id' => $mount->id,
                'name' => $mount->name_fr,
                'is_completed' => in_array($mount->id, $characterMountIds),
                'source' => $mount->source ?? null,
                'category' => $mount->category ?? null,
                'wowhead_id' => $mount->source_spell_id,
                'icon_url' => $mount->icon_url ?? null,
            ];
        }

        return $result;
    }

    /**
     * @param  list<int>  $characterPetIds
     * @return list<CollectibleProgress>
     */
    public function aggregatePets(array $characterPetIds): array
    {
        $result = [];
        foreach (WowPet::all() as $pet) {
            $result[] = [
                'id' => $pet->id,
                'name' => $pet->name_fr,
                'is_completed' => in_array($pet->id, $characterPetIds),
                'source' => $pet->source ?? null,
                'category' => $pet->category ?? null,
                'wowhead_id' => $pet->creature_id,
                'icon_url' => $pet->icon_url ?? null,
            ];
        }

        return $result;
    }

    /**
     * @param  list<int>  $characterDecorIds
     * @return list<DecorProgress>
     */
    public function aggregateDecor(array $characterDecorIds): array
    {
        $result = [];
        $decors = WowDecor::query()
            ->where('is_active', true)
            ->orWhereIn('id', $characterDecorIds)
            ->get();

        foreach ($decors as $decor) {
            $result[] = [
                'id' => $decor->id,
                'name' => $decor->name_fr,
                'is_completed' => in_array($decor->id, $characterDecorIds),
                'item_id' => $decor->item_id,
                'icon_url' => $decor->icon_url,
                'category' => $decor->category,
                'source' => $decor->source,
            ];
        }

        return $result;
    }

    /**
     * Ventile la garde-robe par slot : débloqué/total. Agrégation SQL (jamais de chargement
     * complet du référentiel en mémoire, cf. volumétrie transmog).
     *
     * @param  list<int>  $characterAppearanceIds
     * @return list<AppearanceProgress>
     */
    public function aggregateAppearances(array $characterAppearanceIds): array
    {
        /** @var \Illuminate\Support\Collection<int, object{slot: string, total: int}> $totals */
        $totals = WowAppearance::query()->where('is_active', true)
            ->whereNotNull('slot')
            ->selectRaw('slot, COUNT(*) as total')
            ->groupBy('slot')
            ->orderBy('slot')
            ->get();

        /** @var array<string, int> $completedBySlot */
        $completedBySlot = [];
        if ($characterAppearanceIds !== []) {
            /** @var \Illuminate\Support\Collection<int, object{slot: string, completed: int}> $completedRows */
            $completedRows = WowAppearance::query()->where('is_active', true)
                ->whereNotNull('slot')
                ->whereIn('id', $characterAppearanceIds)
                ->selectRaw('slot, COUNT(*) as completed')
                ->groupBy('slot')
                ->get();

            foreach ($completedRows as $completedRow) {
                $completedBySlot[$completedRow->slot] = $completedRow->completed;
            }
        }

        $result = [];
        foreach ($totals as $total) {
            $result[] = [
                'slot' => $total->slot,
                'category' => self::SLOT_CATEGORIES[$total->slot] ?? null,
                'total' => (int) $total->total,
                'completed' => $completedBySlot[$total->slot] ?? 0,
            ];
        }

        return $result;
    }
}
