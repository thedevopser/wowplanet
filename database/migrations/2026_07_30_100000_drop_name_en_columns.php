<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supprime la colonne name_en, vestige de l'i18n FR/EN retirée du site.
 *
 * Elle n'est plus référencée par aucune ligne de code et, n'ayant jamais été créée par
 * une migration, elle n'existe que sur les bases historiques — d'où les gardes
 * hasColumn() : une base reconstruite depuis les migrations ne la porte pas.
 *
 * Les valeurs ne sont pas restituées par le rollback, mais restent régénérables : les
 * noms anglais figurent dans les JSON SimpleArmory (clé "name").
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = [
        'wow_mounts',
        'wow_pets',
        'wow_decors',
        'wow_achievements',
        'wow_quests',
        'wow_professions',
        'wow_recipes',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasColumn($table, 'name_en')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->dropIndex($table.'_name_en_index');
                $blueprint->dropColumn('name_en');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasColumn($table, 'name_en')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->string('name_en')->nullable()->index();
            });
        }
    }
};
