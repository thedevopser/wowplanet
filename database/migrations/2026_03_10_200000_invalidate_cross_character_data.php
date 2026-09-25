<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('cross_character_data')->update(['fetched_at' => null]);
    }

    public function down(): void
    {
        // No rollback: data will be recomputed on next access
    }
};
