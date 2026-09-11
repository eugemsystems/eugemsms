<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Book K ACA-10 §2. The catalogue entry — a title, not a physical
 * copy; see `library_copies` for the individually-accessioned items a
 * title actually has on the shelf.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_items', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('isbn', 20)->nullable();
            $table->string('title', 300);
            $table->string('author', 200)->nullable();
            $table->string('publisher', 150)->nullable();
            $table->string('edition', 40)->nullable();
            $table->string('classification', 30)->nullable();
            $table->string('item_category', 20);
            $table->foreignId('subject_id')->nullable()->constrained();
            $table->foreignId('grade_level_id')->nullable()->constrained();
            $table->unsignedBigInteger('replacement_cost_minor')->nullable();
            $table->char('currency', 3)->nullable();
            $table->foreignId('cover_image_file_id')->nullable()->constrained('files');
            $table->string('digital_resource_url', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'isbn'], 'library_items_school_isbn_unique');
            $table->index(['school_id', 'subject_id', 'grade_level_id'], 'library_items_subject_grade_idx');

            // sqlite (the test suite's driver) has no fulltext index
            // support at all, so this only runs on MySQL/PostgreSQL.
            if (DB::connection()->getDriverName() !== 'sqlite') {
                $table->fullText(['title', 'author'], 'library_items_title_author_fulltext');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_items');
    }
};
