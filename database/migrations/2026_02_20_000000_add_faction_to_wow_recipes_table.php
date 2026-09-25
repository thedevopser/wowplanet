<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wow_recipes', function (Blueprint $blueprint): void {
            $blueprint->string('faction', 10)->nullable()->after('category_name');
            $blueprint->index('faction');
        });
    }

    public function down(): void
    {
        Schema::table('wow_recipes', function (Blueprint $blueprint): void {
            $blueprint->dropIndex(['faction']);
            $blueprint->dropColumn('faction');
        });
    }
};
