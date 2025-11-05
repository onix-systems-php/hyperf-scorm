<?php
declare(strict_types=1);

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
        Schema::create('scorm_user_sessions', function (Blueprint $table) {
            $table->string('id', 100)->primary();
            $table->foreignId('package_id')->constrained('scorm_packages')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['active', 'suspended', 'completed', 'terminated'])->default('active');
            $table->json('suspend_data')->nullable();
            $table->string('lesson_location')->nullable();
            $table->string('lesson_status')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->integer('session_time')->default(0); // in seconds
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_accessed')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'package_id']);
            $table->index(['package_id', 'status']);
            $table->index(['status', 'last_accessed']);
        });

        Schema::create('scorm_packages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('identifier')->unique();
            $table->string('scorm_version')->default(ScormVersionEnum::SCORM_12->value);
            $table->string('manifest_path');
            $table->string('content_path');
            $table->string('original_filename')->nullable();
            $table->bigInteger('file_size')->unsigned()->nullable();
            $table->string('file_hash', 64)->nullable();
            $table->json('manifest_data');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['identifier', 'scorm_version'], 'idx_identifier_version');
            $table->index(['is_active', 'created_at'], 'idx_active_created');
            $table->index('scorm_version', 'idx_scorm_version');
            $table->fullText(['title', 'description'], 'idx_search_text');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scorm_user_sessions');
        Schema::dropIfExists('scorm_packages');
    }
}
