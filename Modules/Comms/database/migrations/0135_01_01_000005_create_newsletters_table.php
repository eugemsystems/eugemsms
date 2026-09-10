<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book I COM-06 §2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletters', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('issue_number', 20);
            $table->string('title', 200);
            $table->longText('content_html');
            $table->string('audience_scope', 20);
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedBigInteger('archive_file_id')->nullable();
            $table->string('status', 20);
            $table->timestamps();

            $table->unique(['school_id', 'issue_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletters');
    }
};
