<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wow_professions', function (Blueprint $blueprint): void {
            $blueprint->integer('id')->primary(); // Blizzard Profession ID
            $blueprint->string('name_fr');
            $blueprint->string('type'); // 'primary' or 'secondary'
            $blueprint->boolean('is_active')->default(true);
            $blueprint->timestamps();

            $blueprint->index('is_active');
            $blueprint->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wow_professions');
    }
};
