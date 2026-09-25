<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wow_collection_taxonomy', function (Blueprint $blueprint): void {
            $blueprint->string('entity');
            $blueprint->integer('entry_id');
            $blueprint->string('category')->nullable();
            $blueprint->string('source')->nullable();
            $blueprint->primary(['entity', 'entry_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wow_collection_taxonomy');
    }
};
