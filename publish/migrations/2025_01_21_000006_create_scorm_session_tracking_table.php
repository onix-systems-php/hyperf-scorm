<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

class CreateScormSessionTrackingTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('scorm_session_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('scorm_packages')->onDelete('cascade');
            $table->string('session_id', 100)->index();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('sco_id')->nullable()->constrained('scorm_scos')->onDelete('cascade');
            $table->string('element_name'); // e.g., 'cmi.core.lesson_status', 'cmi.core.score.raw'
            $table->text('element_value')->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'element_name'], 'session_element_unique');
            $table->index(['user_id', 'package_id']);
            $table->index(['element_name', 'created_at']);

            $table->foreign('session_id')->references('id')->on('scorm_user_sessions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scorm_session_tracking');
    }
}
