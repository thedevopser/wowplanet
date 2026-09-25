<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cross_character_data', function (Blueprint $blueprint): void {
            $blueprint->string('bnet_user_id')->primary();
            $blueprint->jsonb('data');
            $blueprint->integer('character_count')->default(0);
            $blueprint->timestamp('fetched_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cross_character_data');
    }
};
