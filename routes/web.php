<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\HealthController;
use App\Http\Controllers\Admin\HistoryController;
use App\Http\Controllers\Admin\ImportsController;
use App\Http\Controllers\Admin\ReferenceController;
use App\Http\Controllers\Admin\TaxonomyController;
use App\Http\Controllers\Admin\ToolsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CharacterController;
use App\Http\Controllers\DatabaseController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\PvpController;
use App\Http\Controllers\SeoController;
use App\Http\Localization\AppLocale;
use App\Http\Localization\LocalizedRouteTable;
use Illuminate\Support\Facades\Route;

Route::get('/auth/blizzard/redirect', [AuthController::class, 'redirect']);
Route::get('/auth/blizzard/callback', [AuthController::class, 'callback']);

Route::get('/robots.txt', [SeoController::class, 'robots']);
Route::get('/sitemap.xml', [SeoController::class, 'sitemap']);
Route::get('/sitemap-pages.xml', [SeoController::class, 'sitemapPages']);
Route::get('/sitemap-database.xml', [DatabaseController::class, 'sitemap']);

$localizedRoutes = resolve(LocalizedRouteTable::class);

foreach (AppLocale::cases() as $locale) {
    $path = fn (string $name): string => $localizedRoutes->path($name, $locale);

    Route::get($path('home'), [SeoController::class, 'home']);
    Route::get($path('character'), [CharacterController::class, 'page']);
    Route::get($path('account'), [AccountController::class, 'hub']);
    Route::get($path('database.index'), [DatabaseController::class, 'index']);
    Route::get($path('database.mounts'), [DatabaseController::class, 'mounts']);
    Route::get($path('database.achievements'), [DatabaseController::class, 'achievements']);
    Route::get($path('database.quests'), [DatabaseController::class, 'quests']);
    Route::get($path('database.pets'), [DatabaseController::class, 'pets']);
    Route::get($path('database.decors'), [DatabaseController::class, 'decors']);
    Route::get($path('database.appearances'), [DatabaseController::class, 'appearances']);
    Route::get($path('database.professions'), [DatabaseController::class, 'professions']);
    // Classements PvP : pas un catalogue, donc hors /base-de-donnees et hors DatabaseLayout.
    Route::get($path('pvp'), [PvpController::class, 'leaderboard']);
    Route::get($path('faq'), [SeoController::class, 'faqPage']);
    Route::get($path('terms'), [SeoController::class, 'cguPage']);
    Route::get($path('privacy'), [SeoController::class, 'privacyPage']);
    Route::get($path('addons'), [SeoController::class, 'addonsPage']);
}

Route::permanentRedirect('/my-characters', '/mon-compte');
Route::permanentRedirect('/my-score', '/mon-compte/score');
Route::permanentRedirect('/class-stats', '/mon-compte/classes');

Route::middleware(['throttle:admin', 'admin'])->prefix('admin')->group(function (): void {
    Route::get('/', [DashboardController::class, 'page']);
    Route::get('/imports', [ImportsController::class, 'page']);
    Route::get('/reference', [ReferenceController::class, 'page']);
    Route::get('/taxonomy', [TaxonomyController::class, 'page']);
    Route::get('/health', [HealthController::class, 'page']);
    Route::get('/history', [HistoryController::class, 'page']);
    // Déclarée avant la route à paramètre, qui prendrait sinon « compare » pour un jobId.
    Route::get('/history/compare', [HistoryController::class, 'compare']);
    Route::get('/history/{jobId}', [HistoryController::class, 'entry']);
    Route::get('/tools', [ToolsController::class, 'page']);
});

if (app()->isLocal()) {
    Route::get('/docs', [DocsController::class, 'index']);
    Route::get('/docs/{path}', [DocsController::class, 'file'])->where('path', '.+\.md');
    Route::inertia('/ui', 'UiShowcasePage');
}

// Catch-all : toute URL inconnue (hors api/ et docs/) rend la page 404 Inertia.
Route::get('/{any}', [SeoController::class, 'notFound'])->where('any', '^(?!api/|docs/).*$');
