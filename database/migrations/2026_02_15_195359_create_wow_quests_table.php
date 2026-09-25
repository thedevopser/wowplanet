<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wow_quests', function (Blueprint $blueprint): void {
            $blueprint->integer('id')->primary(); // Blizzard Quest ID
            $blueprint->string('name_fr');
            $blueprint->tinyInteger('expansion_id'); // 0-11
            $blueprint->string('zone_name')->nullable();
            $blueprint->boolean('is_active')->default(true);
            $blueprint->timestamps();

            $blueprint->index(['expansion_id', 'zone_name']);
            $blueprint->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wow_quests');
    }
};
