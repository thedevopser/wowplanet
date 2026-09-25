<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wow_mounts', function (Blueprint $blueprint): void {
            $blueprint->integer('id')->primary(); // Blizzard Mount ID
            $blueprint->string('name_fr');
            $blueprint->string('source')->nullable();
            $blueprint->boolean('is_active')->default(true);
            $blueprint->timestamps();

            $blueprint->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wow_mounts');
    }
};
