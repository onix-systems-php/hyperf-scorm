<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

class CreateScormAttemptsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('scorm_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('scorm_packages')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('status', [
                'not_attempted',
                'incomplete',
                'completed',
                'passed',
                'failed',
                'browsed'
            ])->default('not_attempted');
            $table->decimal('score', 5, 2)->nullable();
            $table->integer('time_spent')->default(0); // in seconds
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'package_id']);
            $table->index(['package_id', 'status']);
            $table->index(['started_at', 'completed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scorm_attempts');
    }
}
