<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_tasks', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('bnet_user_id')->index();
            $blueprint->string('realm_slug');
            $blueprint->string('character_name');
            $blueprint->string('name');
            $blueprint->string('reset_type');
            $blueprint->boolean('is_completed')->default(false);
            $blueprint->timestamp('completed_at')->nullable();
            $blueprint->integer('sort_order')->default(0);
            $blueprint->timestamps();

            $blueprint->index(['bnet_user_id', 'realm_slug', 'character_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_tasks');
    }
};
