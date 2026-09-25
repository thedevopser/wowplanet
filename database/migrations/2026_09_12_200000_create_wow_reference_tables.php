<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wow_ref_faction', function (Blueprint $blueprint): void {
            $blueprint->integer('id')->primary();
            $blueprint->text('name_lang');
            $blueprint->integer('reputation_index')->nullable();
            $blueprint->integer('parent_faction_id')->nullable();
            $blueprint->integer('expansion')->nullable();
            $blueprint->integer('friendship_rep_id')->nullable();
            $blueprint->integer('renown_currency_id')->nullable();
            $blueprint->integer('reputation_max_0')->nullable();
            $blueprint->integer('reputation_max_1')->nullable();
            $blueprint->bigInteger('reputation_race_masks0_0')->nullable();
            $blueprint->bigInteger('reputation_race_masks0_1')->nullable();
        });

        Schema::create('wow_ref_content_tuning', function (Blueprint $blueprint): void {
            $blueprint->integer('id')->primary();
            $blueprint->integer('expansion_id')->nullable();
        });

        Schema::create('wow_ref_area_table', function (Blueprint $blueprint): void {
            $blueprint->integer('id')->primary();
            $blueprint->text('area_name_lang');
            $blueprint->integer('faction_group_mask')->nullable();
        });

        Schema::create('wow_ref_quest_v2_cli_task', function (Blueprint $blueprint): void {
            $blueprint->integer('id')->primary();
            $blueprint->text('quest_title_lang');
            $blueprint->integer('content_tuning_id')->nullable();
            $blueprint->bigInteger('filt_race_masks_0')->nullable();
            $blueprint->bigInteger('filt_race_masks_1')->nullable();
        });

        Schema::create('wow_ref_skill_line_ability', function (Blueprint $blueprint): void {
            $blueprint->integer('id')->primary();
            $blueprint->bigInteger('race_masks_0')->nullable();
            $blueprint->bigInteger('race_masks_1')->nullable();
        });

        Schema::create('wow_ref_currency_types', function (Blueprint $blueprint): void {
            $blueprint->integer('id')->primary();
            $blueprint->text('name_lang');
            $blueprint->integer('max_qty')->nullable();
        });

        Schema::create('wow_reference_downloads', function (Blueprint $blueprint): void {
            $blueprint->string('filename')->primary();
            $blueprint->string('source_table')->index();
            $blueprint->string('build');
            $blueprint->bigInteger('bytes');
            $blueprint->integer('row_count');
            $blueprint->timestamp('downloaded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wow_reference_downloads');
        Schema::dropIfExists('wow_ref_currency_types');
        Schema::dropIfExists('wow_ref_skill_line_ability');
        Schema::dropIfExists('wow_ref_quest_v2_cli_task');
        Schema::dropIfExists('wow_ref_area_table');
        Schema::dropIfExists('wow_ref_content_tuning');
        Schema::dropIfExists('wow_ref_faction');
    }
};
