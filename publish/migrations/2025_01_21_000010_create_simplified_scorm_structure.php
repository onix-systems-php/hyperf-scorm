<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

class CreateSimplifiedScormStructure extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop old complex tables
        Schema::dropIfExists('scorm_session_tracking');
        Schema::dropIfExists('scorm_tracking');
        Schema::dropIfExists('scorm_attempts');
        Schema::dropIfExists('scorm_scos');

        // Keep simplified scorm_packages
        if (!Schema::hasTable('scorm_packages')) {
            Schema::create('scorm_packages', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('version')->nullable();
                $table->string('identifier')->unique();
                $table->string('manifest_path');
                $table->string('content_path');
                $table->json('manifest_data')->nullable();
                $table->enum('scorm_version', ['1.2', '2004'])->default('1.2');
                $table->timestamps();
                $table->softDeletes();

                $table->index(['identifier', 'scorm_version']);
            });
        }

        // Simplified user sessions
        if (!Schema::hasTable('scorm_user_sessions')) {
            Schema::create('scorm_user_sessions', function (Blueprint $table) {
                $table->string('id', 100)->primary();
                $table->foreignId('package_id')->constrained('scorm_packages')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->enum('status', ['active', 'suspended', 'completed', 'terminated'])->default('active');
                $table->json('suspend_data')->nullable();
                $table->string('current_location')->nullable(); // Where user currently is
                $table->timestamp('started_at')->nullable();
                $table->timestamp('last_accessed')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'package_id']);
                $table->index(['package_id', 'status']);
                $table->index(['status', 'last_accessed']);
            });
        }

        // Single table for all user activities
        Schema::create('scorm_activities', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 100);
            $table->foreignId('package_id')->constrained('scorm_packages')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('activity_type', [
                'question_answer',      // Ответ на вопрос
                'lesson_complete',      // Завершение урока
                'interaction',          // Любое взаимодействие
                'score_update',         // Обновление оценки
                'location_change',      // Изменение позиции в курсе
                'session_start',        // Начало сессии
                'session_suspend',      // Приостановка
                'session_terminate'     // Завершение сессии
            ]);
            $table->json('activity_data'); // Детали активности (вопрос, ответ, результат, etc.)
            $table->string('scorm_element')->nullable(); // CMI элемент (cmi.interactions.0.result)
            $table->text('scorm_value')->nullable(); // Значение CMI элемента
            $table->timestamp('activity_timestamp');
            $table->timestamps();

            $table->index(['session_id', 'activity_type']);
            $table->index(['user_id', 'package_id', 'activity_timestamp']);
            $table->index(['activity_type', 'activity_timestamp']);
            $table->index(['scorm_element']);

            $table->foreign('session_id')->references('id')->on('scorm_user_sessions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scorm_activities');
        Schema::dropIfExists('scorm_user_sessions');
        Schema::dropIfExists('scorm_packages');
    }
}
