<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;
use OnixSystemsPHP\HyperfScorm\Enum\ScormVersionEnum;

class CreateScormPackagesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('scorm_packages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('version')->default(ScormVersionEnum::SCORM_12->value);
            $table->string('identifier')->unique()->nullable();
            $table->string('content_path');
            $table->string('domain');
            $table->string('launch_url');
            $table->json('manifest_data');
            $table->string('scorm_version');
            $table->boolean('is_active')->default(true);
            $table->string('original_filename')->nullable();
            $table->bigInteger('file_size')->unsigned()->nullable();
            $table->string('file_hash', 64)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['identifier', 'scorm_version'], 'idx_identifier_version');
            $table->index(['is_active', 'created_at'], 'idx_active_created');
            $table->index('scorm_version', 'idx_scorm_version');
            $table->fullText(['title', 'description'], 'idx_search_text');
        });

        Schema::create('scorm_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('package_id');
            $table->foreign('package_id')
                ->references('id')->on('scorm_packages')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->string('status', 25)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_accessed')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('session_token')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->string('lesson_status', 50)->nullable();
            $table->string('lesson_location', 1000)->nullable();
            $table->string('lesson_credit')->default('credit');
            $table->string('lesson_entry')->nullable();
            $table->string('lesson_mode')->default('normal');
            $table->decimal('score_raw', 8, 2)->nullable();
            $table->decimal('score_scaled', 5, 4)->nullable();
            $table->decimal('best_score', 8, 2)->nullable();
            $table->string('completion_status', 50)->nullable();
            $table->string('success_status', 50)->nullable();
            $table->string('exit_mode', 50)->nullable();
            $table->unsignedInteger('session_time')->default(0);
            $table->unsignedInteger('total_time')->default(0);
            $table->unsignedInteger('session_time_seconds')->default(0);
            $table->unsignedInteger('total_time_seconds')->default(0);
            $table->unsignedInteger('interactions_count')->default(0);
            $table->unsignedInteger('interactions_processed')->default(0);
            $table->timestamp('last_interaction_at')->nullable();
            $table->unsignedInteger('restart_count')->default(0);
            $table->string('scorm_version', 10)->default('1.2');
            $table->text('suspend_data')->nullable();
            $table->text('launch_data')->nullable();
            $table->text('comments')->nullable();
            $table->text('comments_from_lms')->nullable();
            $table->string('student_name')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'package_id']);
            $table->index('session_token');
            $table->index('started_at');
            $table->index('completed_at');
        });

        Schema::create('scorm_interactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('session_id');
            $table->foreign('session_id')
                ->references('id')->on('scorm_sessions')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->bigInteger('interaction_id')->nullable();
            $table->string('interaction_type', 250)->nullable();
            $table->string('type', 50);
            $table->text('description');
            $table->jsonb('learner_response');
            $table->jsonb('correct_response');
            $table->jsonb('objectives');
            $table->string('result');
            $table->integer('weighting');
            $table->integer('latency');
            $table->timestamp('interaction_timestamp');
            $table->timestamps();

            $table->index(['session_id']);
            $table->index(['type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scorm_user_sessions');
        Schema::dropIfExists('scorm_interactions');
        Schema::dropIfExists('scorm_packages');
    }
}
