<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

class CreateScormUserSessionsTable extends Migration
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scorm_user_sessions');
    }
}
