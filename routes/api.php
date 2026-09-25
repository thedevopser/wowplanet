<?php

use App\Http\Controllers\Admin\HealthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CharacterController;
use App\Http\Controllers\CharacterFavoriteController;
use App\Http\Controllers\CharacterTaskController;
use App\Http\Controllers\PvpController;
use App\Http\Controllers\TalentController;
use App\Http\Controllers\UserCharacterController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:api')->group(function (): void {
    Route::get('/character/{realm}/{name}/talents', [TalentController::class, 'show']);
    Route::get('/character/{realm}/{name}/pvp', [PvpController::class, 'show']);
    Route::get('/character/{realm}/{name}', [CharacterController::class, 'show']);
});

Route::middleware('throttle:authenticated')->group(function (): void {
    Route::post('/auth/logout', [UserCharacterController::class, 'logout']);
    Route::get('/user/characters', [UserCharacterController::class, 'index']);
    Route::get('/class-icons', [UserCharacterController::class, 'classIcons']);
    Route::get('/account/score', [UserCharacterController::class, 'accountScore']);
    Route::post('/account/score/refresh', [UserCharacterController::class, 'refreshAccountScore']);
    Route::get('/account/cross-character', [UserCharacterController::class, 'crossCharacter']);
    Route::get('/account/cross-character/{jobId}', [UserCharacterController::class, 'crossCharacterStatus']);
    Route::get('/account/cross-character-data', [UserCharacterController::class, 'crossCharacterData']);

    Route::get('/character-tasks', [CharacterTaskController::class, 'index']);
    Route::post('/character-tasks', [CharacterTaskController::class, 'store']);
    Route::put('/character-tasks/{id}', [CharacterTaskController::class, 'update']);
    Route::delete('/character-tasks/{id}', [CharacterTaskController::class, 'destroy']);

    Route::get('/character-favorites', [CharacterFavoriteController::class, 'index']);
    Route::post('/character-favorites', [CharacterFavoriteController::class, 'store']);
    Route::delete('/character-favorites/{realm}/{name}', [CharacterFavoriteController::class, 'destroy']);
});

Route::middleware(['throttle:admin', 'admin'])->prefix('admin')->group(function (): void {
    Route::get('/status', [AdminController::class, 'status']);
    Route::post('/import', [AdminController::class, 'import']);
    // Déclarée avant la route à paramètre, qui prendrait sinon « current » pour un jobId.
    Route::get('/import/current', [AdminController::class, 'currentImport']);
    Route::get('/import/{jobId}', [AdminController::class, 'importStatus']);
    Route::post('/import/{jobId}/pause', [AdminController::class, 'pauseImport']);
    Route::post('/import/{jobId}/resume', [AdminController::class, 'resumeImport']);
    Route::post('/import/{jobId}/cancel', [AdminController::class, 'cancelImport']);
    Route::post('/build-check', [AdminController::class, 'checkBuilds']);
    Route::post('/clear-cache', [AdminController::class, 'clearCache']);
    Route::post('/maintenance', [AdminController::class, 'maintenance']);
    Route::post('/reference/sync', [AdminController::class, 'syncReference']);
    Route::post('/reference/purge', [AdminController::class, 'purgeReference']);
    Route::post('/taxonomy/arbitrate', [AdminController::class, 'arbitrateTaxonomy']);
    Route::post('/taxonomy/load', [AdminController::class, 'loadTaxonomySnapshot']);
    Route::get('/taxonomy/snapshot', [AdminController::class, 'downloadTaxonomySnapshot']);
    Route::get('/health/queue', [HealthController::class, 'queue']);
    Route::post('/failed-jobs/{uuid}/retry', [AdminController::class, 'retryFailedJob']);
    Route::delete('/failed-jobs/{uuid}', [AdminController::class, 'forgetFailedJob']);
    Route::post('/discord', [AdminController::class, 'discord']);
});
