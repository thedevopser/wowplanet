<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wow_mounts', function (Blueprint $blueprint): void {
            $blueprint->unsignedInteger('display_id')->nullable()->after('source_spell_id');
        });
    }

    public function down(): void
    {
        Schema::table('wow_mounts', function (Blueprint $blueprint): void {
            $blueprint->dropColumn('display_id');
        });
    }
};
