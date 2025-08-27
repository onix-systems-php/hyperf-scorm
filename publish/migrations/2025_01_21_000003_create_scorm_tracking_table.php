<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

class CreateScormTrackingTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('scorm_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('scorm_packages')->onDelete('cascade');
            $table->foreignId('sco_id')->constrained('scorm_scos')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('attempt_id')->nullable()->constrained('scorm_attempts')->onDelete('cascade');
            $table->string('element_name'); // e.g., 'cmi.core.lesson_status', 'cmi.core.score.raw'
            $table->text('element_value')->nullable();
            $table->timestamps();

            $table->unique(['package_id', 'sco_id', 'user_id', 'attempt_id', 'element_name'], 'scorm_tracking_unique');
            $table->index(['user_id', 'package_id']);
            $table->index(['element_name', 'element_value']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scorm_tracking');
    }
}
