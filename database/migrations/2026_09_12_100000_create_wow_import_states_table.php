<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wow_import_states', function (Blueprint $blueprint): void {
            $blueprint->string('entity')->primary();
            $blueprint->string('build');
            $blueprint->string('last_modified')->nullable();
            $blueprint->timestamp('imported_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wow_import_states');
    }
};
