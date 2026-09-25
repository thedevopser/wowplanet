<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wow_recipes', function (Blueprint $blueprint): void {
            $blueprint->integer('id')->primary(); // Blizzard Recipe ID
            $blueprint->string('name_fr');
            $blueprint->integer('profession_id');
            $blueprint->tinyInteger('expansion_id'); // 0-11
            $blueprint->string('category_name')->nullable();
            $blueprint->boolean('is_active')->default(true);
            $blueprint->timestamps();

            $blueprint->index(['profession_id', 'expansion_id']);
            $blueprint->index('is_active');
            $blueprint->foreign('profession_id')->references('id')->on('wow_professions');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wow_recipes');
    }
};
