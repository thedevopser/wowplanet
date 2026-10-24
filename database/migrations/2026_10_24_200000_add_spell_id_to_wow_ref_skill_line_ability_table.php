<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wow_ref_skill_line_ability', function (Blueprint $blueprint): void {
            $blueprint->integer('spell_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('wow_ref_skill_line_ability', function (Blueprint $blueprint): void {
            $blueprint->dropColumn('spell_id');
        });
    }
};
