<?php

declare(strict_types=1);

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

class AddCmiDataToScormAttempts extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('scorm_attempts', function (Blueprint $table) {
            $table->jsonb('cmi_data')->nullable()->after('completed_at')->comment('SCORM CMI tracking data');
            $table->string('lesson_location')->nullable()->after('cmi_data')->comment('Current lesson location');
            $table->string('lesson_status')->default('not attempted')->after('lesson_location')->comment('Lesson completion status');
            $table->text('suspend_data')->nullable()->after('lesson_status')->comment('Data to persist between sessions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scorm_attempts', function (Blueprint $table) {
            $table->dropColumn(['cmi_data', 'lesson_location', 'lesson_status', 'suspend_data']);
        });
    }
}
