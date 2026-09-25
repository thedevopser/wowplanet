<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_errors', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('level', 16);
            $blueprint->text('message');
            $blueprint->string('exception_class')->nullable();
            $blueprint->string('location')->nullable();
            $blueprint->timestamp('occurred_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_errors');
    }
};
