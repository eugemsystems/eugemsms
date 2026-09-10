<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-06 §2/BR-CORE-06-007. Editing a template creates a new
 * version row rather than mutating this one — `version` plus
 * `is_active` together identify the current one per (school,
 * template_type[, section]).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('school_sections')->nullOnDelete();
            $table->string('template_type', 40);
            $table->string('name', 150);
            $table->smallInteger('version')->default(1);
            $table->longText('content');
            $table->longText('styles')->nullable();
            $table->string('page_size', 20)->default('A4');
            $table->string('orientation', 20)->default('portrait');
            $table->json('margins')->nullable();
            $table->longText('header_content')->nullable();
            $table->longText('footer_content')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'template_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_templates');
    }
};
