<?php

declare(strict_types=1);

use App\Infrastructure\Taxonomy\CollectionEntity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wow_collection_taxonomy', function (Blueprint $blueprint): void {
            $blueprint->boolean('obtainable')->default(true);
        });

        // Le chargement de la taxonomie est additif : sans cette reprise, les décorations
        // déjà curées hors d'atteinte repasseraient obtenables et fausseraient le
        // dénominateur. Leur marqueur vit aujourd'hui dans `wow_decors.is_active`.
        DB::table('wow_collection_taxonomy')
            ->where('entity', CollectionEntity::Decor->value)
            ->whereIn('entry_id', DB::table('wow_decors')->where('is_active', false)->select('id'))
            ->update(['obtainable' => false]);
    }

    public function down(): void
    {
        Schema::table('wow_collection_taxonomy', function (Blueprint $blueprint): void {
            $blueprint->dropColumn('obtainable');
        });
    }
};
