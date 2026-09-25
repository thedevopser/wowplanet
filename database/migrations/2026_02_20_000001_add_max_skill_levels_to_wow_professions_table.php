<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wow_professions', function (Blueprint $blueprint): void {
            $blueprint->jsonb('max_skill_levels')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('wow_professions', function (Blueprint $blueprint): void {
            $blueprint->dropColumn('max_skill_levels');
        });
    }
};
