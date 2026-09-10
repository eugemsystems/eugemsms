<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-02 §2. BR-CORE-02-002: a school must have at least one
 * section before any grade level can be created.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_sections', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->string('type', 20);
            $table->smallInteger('sort_order')->default(0);
            $table->foreignId('head_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_sections');
    }
};
