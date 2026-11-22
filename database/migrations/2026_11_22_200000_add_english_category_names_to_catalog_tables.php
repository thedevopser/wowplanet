<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les catégories des hauts-faits et des recettes suivent elles aussi la langue de la page.
 * Nullable, puisqu'une ligne n'en a qu'au prochain import.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const array TABLES = ['wow_achievements', 'wow_recipes'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->string('category_name_en')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('category_name_en');
            });
        }
    }
};
