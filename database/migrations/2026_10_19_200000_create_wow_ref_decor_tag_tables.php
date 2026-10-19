<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wow_ref_data_tag_x_house_decor_record', function (Blueprint $blueprint): void {
            $blueprint->integer('id')->primary();
            $blueprint->integer('data_tag_id')->nullable()->index();
            $blueprint->integer('house_decor_id')->nullable();
        });

        Schema::create('wow_ref_data_tag_group_x_data_tag', function (Blueprint $blueprint): void {
            $blueprint->integer('id')->primary();
            $blueprint->integer('data_tag_group_id')->nullable();
            $blueprint->integer('data_tag')->nullable()->index();
        });

        Schema::create('wow_ref_data_tag_info', function (Blueprint $blueprint): void {
            $blueprint->integer('id')->primary();
            $blueprint->integer('data_tag')->nullable()->index();
            $blueprint->text('description_lang');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wow_ref_data_tag_info');
        Schema::dropIfExists('wow_ref_data_tag_group_x_data_tag');
        Schema::dropIfExists('wow_ref_data_tag_x_house_decor_record');
    }
};
