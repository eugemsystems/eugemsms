<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-03 §6/BR-PPL-03-021. A guardian's requested change to phone, email or address, held until staff approve it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardian_contact_updates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained('guardians')->cascadeOnDelete();
            $table->json('changes');
            $table->string('status', 20);
            $table->foreignId('requested_by')->nullable()->constrained('users');
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'guardian_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_contact_updates');
    }
};
