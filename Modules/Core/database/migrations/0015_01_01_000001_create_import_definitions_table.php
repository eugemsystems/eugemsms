<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-11 §2. "Registered by modules" — code owns the list,
 * this table mirrors it, same split as `file_categories` (CORE-10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_definitions', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('label', 150);
            $table->string('module_code', 20);
            $table->string('importer_class', 255);
            $table->text('description')->nullable();
            $table->string('required_permission', 120);
            $table->json('depends_on')->nullable();
            $table->boolean('is_rollbackable')->default(true);
            $table->smallInteger('sort_order')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_definitions');
    }
};
