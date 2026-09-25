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
        Schema::create('character_visits', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('realm_slug', 100);
            $blueprint->string('character_name', 100);
            $blueprint->string('display_name', 100)->nullable();
            $blueprint->string('display_realm', 100)->nullable();
            $blueprint->string('class_name', 50)->nullable();
            $blueprint->unsignedSmallInteger('level')->nullable();
            $blueprint->timestamp('last_visited_at');
            $blueprint->timestamps();

            $blueprint->unique(['realm_slug', 'character_name']);
            $blueprint->index('last_visited_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('character_visits');
    }
};
