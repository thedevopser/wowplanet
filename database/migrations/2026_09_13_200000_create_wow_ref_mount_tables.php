<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wow_ref_mount', function (Blueprint $blueprint): void {
            $blueprint->integer('id')->primary();
            $blueprint->integer('source_spell_id')->nullable();
        });

        // SpellMisc porte une ligne par difficulté : le sort n'est pas la clé, et la
        // jointure des icônes de montures le parcourt par SpellID.
        Schema::create('wow_ref_spell_misc', function (Blueprint $blueprint): void {
            $blueprint->integer('id')->primary();
            $blueprint->integer('spell_id')->nullable()->index();
            $blueprint->integer('spell_icon_file_data_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wow_ref_spell_misc');
        Schema::dropIfExists('wow_ref_mount');
    }
};
