<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Noms anglais des réputations, lus dans la table DB2 Faction en enUS, à côté de la
 * table française qui porte le reste de leurs données.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wow_ref_faction_en', function (Blueprint $blueprint): void {
            $blueprint->integer('id')->primary();
            $blueprint->text('name_lang');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wow_ref_faction_en');
    }
};
