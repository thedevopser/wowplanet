<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('localized_names');
    }

    public function down(): void
    {
        Schema::create('localized_names', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('type');
            $blueprint->unsignedInteger('blizzard_id');
            $blueprint->string('locale', 10)->default('fr_FR');
            $blueprint->string('name');
            $blueprint->timestamps();
            $blueprint->unique(['type', 'blizzard_id', 'locale']);
        });
    }
};
