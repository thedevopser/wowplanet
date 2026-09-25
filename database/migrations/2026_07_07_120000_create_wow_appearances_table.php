<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wow_appearances', function (Blueprint $blueprint): void {
            $blueprint->integer('id')->primary(); // Blizzard ItemAppearance ID
            $blueprint->string('name_fr');
            $blueprint->string('slot')->nullable(); // ex: HEAD, SHOULDER, WEAPON
            $blueprint->string('category')->nullable(); // Armure / Arme
            $blueprint->unsignedTinyInteger('quality')->nullable(); // OverallQualityID
            $blueprint->unsignedInteger('item_id')->nullable(); // item représentatif (wowhead/media)
            $blueprint->unsignedInteger('icon_file_data_id')->nullable(); // DefaultIconFileDataID (résolution icône différée)
            $blueprint->string('icon_url')->nullable();
            $blueprint->unsignedInteger('expansion_id')->nullable();
            $blueprint->string('source')->nullable();
            $blueprint->boolean('is_active')->default(true);
            $blueprint->timestamps();

            $blueprint->index('is_active');
            $blueprint->index('slot');
            $blueprint->index('quality');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wow_appearances');
    }
};
