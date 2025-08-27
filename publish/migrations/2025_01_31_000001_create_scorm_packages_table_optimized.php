<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;
use OnixSystemsPHP\HyperfScorm\Enum\ScormVersionEnum;

class CreateScormPackagesTableOptimized extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('scorm_packages', function (Blueprint $table) {
            // Основные поля
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('identifier')->unique();
            $table->string('scorm_version')->default(ScormVersionEnum::SCORM_12->value);

            // Файловая система
            $table->string('manifest_path');
            $table->string('content_path');
            $table->string('original_filename')->nullable();
            $table->bigInteger('file_size')->unsigned()->nullable();
            $table->string('file_hash', 64)->nullable();

            // SCORM данные
            $table->json('manifest_data');

            // Статус
            $table->boolean('is_active')->default(true);

            // Системные поля
            $table->timestamps();
            $table->softDeletes();

            // Индексы
            $table->index(['identifier', 'scorm_version'], 'idx_identifier_version');
            $table->index(['is_active', 'created_at'], 'idx_active_created');
            $table->index('scorm_version', 'idx_scorm_version');

            // Полнотекстовый поиск
            $table->fullText(['title', 'description'], 'idx_search_text');
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
