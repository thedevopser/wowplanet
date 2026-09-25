<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

use App\Models\CharacterVisit;
use App\Models\User;
use App\Models\WowAchievement;
use App\Models\WowDecor;
use App\Models\WowMount;
use App\Models\WowPet;
use App\Models\WowProfession;
use App\Models\WowQuest;
use App\Models\WowRecipe;

// ─── WowMount ────────────────────────────────────────────────

test('WowMount factory creates valid instance', function (): void {
    $mount = WowMount::factory()->create();

    expect($mount)->toBeInstanceOf(WowMount::class)
        ->and($mount->is_active)->toBeBool()
        ->and($mount->incrementing)->toBeFalse();
});

// ─── WowPet ──────────────────────────────────────────────────

test('WowPet factory creates valid instance', function (): void {
    $pet = WowPet::factory()->create();

    expect($pet)->toBeInstanceOf(WowPet::class)
        ->and($pet->is_active)->toBeBool()
        ->and($pet->incrementing)->toBeFalse();
});

// ─── WowAchievement ─────────────────────────────────────────

test('WowAchievement factory creates valid instance', function (): void {
    $achievement = WowAchievement::factory()->create();

    expect($achievement)->toBeInstanceOf(WowAchievement::class)
        ->and($achievement->is_active)->toBeBool()
        ->and($achievement->incrementing)->toBeFalse();
});

// ─── WowDecor ────────────────────────────────────────────────

test('WowDecor factory creates valid instance', function (): void {
    $decor = WowDecor::factory()->create();

    expect($decor)->toBeInstanceOf(WowDecor::class)
        ->and($decor->is_active)->toBeBool()
        ->and($decor->incrementing)->toBeFalse();
});

// ─── WowProfession ──────────────────────────────────────────

test('WowProfession factory creates valid instance', function (): void {
    $profession = WowProfession::factory()->create();

    expect($profession)->toBeInstanceOf(WowProfession::class)
        ->and($profession->is_active)->toBeBool()
        ->and($profession->incrementing)->toBeFalse();
});

// ─── WowRecipe ───────────────────────────────────────────────

test('WowRecipe factory creates valid instance', function (): void {
    $profession = WowProfession::factory()->create();

    /** @var WowRecipe $recipe */
    $recipe = WowRecipe::factory()->create([
        'profession_id' => $profession->id,
    ]);

    expect($recipe)->toBeInstanceOf(WowRecipe::class)
        ->and($recipe->is_active)->toBeBool()
        ->and($recipe->incrementing)->toBeFalse()
        ->and($recipe->profession_id)->toBe($profession->id);
});

// ─── WowQuest ────────────────────────────────────────────────

test('WowQuest factory creates valid instance', function (): void {
    $quest = WowQuest::factory()->create();

    expect($quest)->toBeInstanceOf(WowQuest::class)
        ->and($quest->is_active)->toBeBool()
        ->and($quest->incrementing)->toBeFalse()
        ->and($quest->expansion_id)->toBeInt();
});

// ─── User ────────────────────────────────────────────────────

test('User factory creates valid instance', function (): void {
    $user = User::factory()->create();

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->incrementing)->toBeTrue()
        ->and($user->name)->toBeString()
        ->and($user->email)->toBeString();
});

// ─── CharacterVisit ──────────────────────────────────────────

test('CharacterVisit factory creates valid instance', function (): void {
    $visit = CharacterVisit::factory()->create();

    expect($visit)->toBeInstanceOf(CharacterVisit::class)
        ->and($visit->realm_slug)->toBeString()
        ->and($visit->character_name)->toBeString()
        ->and($visit->display_name)->toBeString()
        ->and($visit->display_realm)->toBeString()
        ->and($visit->class_name)->toBeString()
        ->and($visit->level)->toBe(80)
        ->and($visit->last_visited_at)->toBeInstanceOf(\Carbon\Carbon::class);
});
