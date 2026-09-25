<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wow_achievements', function (Blueprint $blueprint): void {
            $blueprint->string('icon_url')->nullable()->after('category_name');
            $blueprint->unsignedTinyInteger('points')->default(0)->after('icon_url');
            $blueprint->string('faction', 10)->nullable()->after('points');
        });

        Schema::table('wow_pets', function (Blueprint $blueprint): void {
            $blueprint->string('category')->nullable()->after('name_fr');
        });

        Schema::table('wow_mounts', function (Blueprint $blueprint): void {
            $blueprint->dropColumn('display_id');
        });
    }

    public function down(): void
    {
        Schema::table('wow_achievements', function (Blueprint $blueprint): void {
            $blueprint->dropColumn(['icon_url', 'points', 'faction']);
        });

        Schema::table('wow_pets', function (Blueprint $blueprint): void {
            $blueprint->dropColumn('category');
        });

        Schema::table('wow_mounts', function (Blueprint $blueprint): void {
            $blueprint->unsignedInteger('display_id')->nullable()->after('source_spell_id');
        });
    }
};
