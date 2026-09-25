<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_history', function (Blueprint $blueprint): void {
            $blueprint->string('job_id')->primary();
            $blueprint->string('trigger');
            $blueprint->string('mode', 16);
            $blueprint->string('status', 16);
            $blueprint->timestamp('started_at')->index();
            $blueprint->timestamp('finished_at')->nullable();
            $blueprint->unsignedInteger('budget_used')->default(0);
        });

        Schema::create('import_history_steps', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('job_id');
            $blueprint->string('stage', 32);
            $blueprint->string('status', 16);
            $blueprint->unsignedInteger('created')->default(0);
            $blueprint->unsignedInteger('updated')->default(0);
            $blueprint->unsignedInteger('deleted')->default(0);
            $blueprint->unsignedInteger('api_calls')->default(0);
            $blueprint->unsignedInteger('duration_ms')->default(0);
            // Ce que les tables de l'étape pèsent à la clôture de l'import : c'est ce qu'on
            // compare d'un import au suivant. Nul tant que l'import tourne, et pour le
            // socle, chargé dans des tables que l'étape ne compte pas.
            $blueprint->unsignedInteger('rows_after')->nullable();
            $blueprint->text('error')->nullable();

            $blueprint->foreign('job_id')->references('job_id')->on('import_history')->cascadeOnDelete();
            $blueprint->unique(['job_id', 'stage']);
            $blueprint->index('stage');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_history_steps');
        Schema::dropIfExists('import_history');
    }
};
