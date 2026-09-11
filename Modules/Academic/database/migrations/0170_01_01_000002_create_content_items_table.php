<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book K ACA-08 §2/§3/BR-ACA-08-003. `is_downloadable_offline` is the
 * data-cost-aware delivery flag — false means "stream on request",
 * deliberately used for large video content a school wants viewable
 * but not auto-queued into a low-end phone's offline cache.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_items', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_space_id')->constrained()->cascadeOnDelete();
            $table->string('content_type', 20);
            $table->string('title', 200);
            $table->foreignId('file_id')->nullable()->constrained('files');
            $table->string('external_url', 500)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->boolean('is_downloadable_offline')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->smallInteger('sort_order')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'course_space_id', 'published_at'], 'content_items_space_published_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_items');
    }
};
