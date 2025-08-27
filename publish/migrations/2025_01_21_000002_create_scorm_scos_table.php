<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

class CreateScormScosTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('scorm_scos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('scorm_packages')->onDelete('cascade');
            $table->string('identifier');
            $table->string('title');
            $table->string('launch_url');
            $table->json('prerequisites')->nullable();
            $table->json('parameters')->nullable();
            $table->string('mastery_score')->nullable();
            $table->string('max_time_allowed')->nullable();
            $table->string('time_limit_action')->nullable();
            $table->timestamps();

            $table->unique(['package_id', 'identifier']);
            $table->index(['package_id', 'title']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scorm_scos');
    }
}
