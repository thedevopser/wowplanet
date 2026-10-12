<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wow_quests', function (Blueprint $blueprint): void {
            $blueprint->timestamp('mirror_unresolved_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('wow_quests', function (Blueprint $blueprint): void {
            $blueprint->dropColumn('mirror_unresolved_at');
        });
    }
};
