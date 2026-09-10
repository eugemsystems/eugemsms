<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-10 §2/BR-CORE-10-004. `path` is always
 * `school/{school_id}/{category}/{ulid}.{ext}` — never derived from
 * user input, so cross-school path traversal is structurally
 * impossible, not merely validated against.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 30)->default('s3');
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->string('extension', 10);
            $table->bigInteger('size_bytes');
            $table->char('hash', 64);
            $table->string('category', 60);
            $table->string('attachable_type')->nullable();
            $table->unsignedBigInteger('attachable_id')->nullable();
            $table->boolean('is_sensitive')->default(false);
            $table->string('scan_status', 20)->default('pending');
            $table->string('scan_result', 255)->nullable();
            $table->json('variants')->nullable();
            $table->date('expires_on')->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'category']);
            $table->index(['attachable_type', 'attachable_id']);
            $table->index(['school_id', 'hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
