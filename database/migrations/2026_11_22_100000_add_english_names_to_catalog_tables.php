<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le catalogue suit la langue de la page : chaque table porte le nom anglais à côté du nom
 * français. Nullable, puisqu'une ligne n'en a qu'au prochain import.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const array TABLES = [
        'wow_mounts',
        'wow_pets',
        'wow_decors',
        'wow_achievements',
        'wow_quests',
        'wow_professions',
        'wow_recipes',
        'wow_appearances',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->string('name_en')->nullable()->index();
            });
        }

        Schema::table('wow_quests', function (Blueprint $blueprint): void {
            $blueprint->string('zone_name_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('wow_quests', function (Blueprint $blueprint): void {
            $blueprint->dropColumn('zone_name_en');
        });

        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('name_en');
            });
        }
    }
};
