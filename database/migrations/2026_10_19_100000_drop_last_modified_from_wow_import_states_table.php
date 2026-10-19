<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wow_import_states', function (Blueprint $blueprint): void {
            $blueprint->dropColumn('last_modified');
        });
    }

    public function down(): void
    {
        Schema::table('wow_import_states', function (Blueprint $blueprint): void {
            $blueprint->string('last_modified')->nullable();
        });
    }
};
