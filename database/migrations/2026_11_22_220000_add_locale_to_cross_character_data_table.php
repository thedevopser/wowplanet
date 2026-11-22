<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les données croisées portent des libellés de Blizzard (paliers de réputation, renom) :
 * elles sont gardées par compte et par langue. Les lignes existantes ont été calculées en
 * français.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cross_character_data', function (Blueprint $blueprint): void {
            $blueprint->string('locale', 2)->default('fr');
        });

        Schema::table('cross_character_data', function (Blueprint $blueprint): void {
            $blueprint->dropPrimary();
            $blueprint->primary(['bnet_user_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::table('cross_character_data', function (Blueprint $blueprint): void {
            $blueprint->dropPrimary();
        });

        Schema::table('cross_character_data', function (Blueprint $blueprint): void {
            $blueprint->dropColumn('locale');
            $blueprint->primary('bnet_user_id');
        });
    }
};
