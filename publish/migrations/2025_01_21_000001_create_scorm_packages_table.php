<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;
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
            $table->string('version')->nullable();
            $table->string('identifier')->unique();
            $table->string('manifest_path');
            $table->string('content_path');
            $table->json('manifest_data')->nullable();
            $table->string('scorm_version')->default(ScormVersionEnum::SCORM_2004->value);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['identifier', 'scorm_version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scorm_packages');
    }
}
