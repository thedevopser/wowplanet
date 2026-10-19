<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wow_decors', function (Blueprint $blueprint): void {
            $blueprint->string('suggested_category')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('wow_decors', function (Blueprint $blueprint): void {
            $blueprint->dropColumn('suggested_category');
        });
    }
};
