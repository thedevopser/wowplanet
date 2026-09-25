<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wow_upstream_builds', function (Blueprint $blueprint): void {
            $blueprint->string('source')->primary();
            $blueprint->string('build')->nullable();
            // La date de la valeur affichée, pas celle de la dernière tentative : un échec
            // ne la touche pas, sans quoi le panneau daterait d'aujourd'hui un build lu
            // la semaine dernière.
            $blueprint->timestamp('checked_at')->nullable();
            $blueprint->string('outcome');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wow_upstream_builds');
    }
};
