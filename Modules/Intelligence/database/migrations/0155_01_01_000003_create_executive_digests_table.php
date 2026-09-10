<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J INT-02 §2 ⭐/BR-INT-02-004/005.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('executive_digests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipient_user_id')->constrained('users');
            $table->date('digest_date');
            $table->json('content_summary');
            $table->string('delivered_via', 20);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'recipient_user_id', 'digest_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('executive_digests');
    }
};
