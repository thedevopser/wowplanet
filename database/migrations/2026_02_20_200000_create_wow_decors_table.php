<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wow_decors', function (Blueprint $blueprint): void {
            $blueprint->integer('id')->primary(); // Blizzard Decor ID
            $blueprint->string('name_fr');
            $blueprint->unsignedInteger('item_id')->nullable(); // Blizzard Item ID (for media/wowhead)
            $blueprint->string('icon_url')->nullable();
            $blueprint->boolean('is_active')->default(true);
            $blueprint->timestamps();

            $blueprint->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wow_decors');
    }
};
