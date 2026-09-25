<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wow_decors', function (Blueprint $blueprint): void {
            $blueprint->string('category')->nullable()->after('name_fr');
            $blueprint->string('source')->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('wow_decors', function (Blueprint $blueprint): void {
            $blueprint->dropColumn(['category', 'source']);
        });
    }
};
