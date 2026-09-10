<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-06 §2/BR-OPS-06-006/007.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('key_issues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('key_id')->constrained('keys_and_cards');
            $table->foreignId('issued_to_staff_id')->nullable()->constrained('staff');
            $table->foreignId('issued_to_contractor_id')->nullable()->constrained('contractors');
            $table->timestamp('issued_at');
            $table->foreignId('issued_by')->constrained('users');
            $table->date('due_back_on')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users');
            $table->string('status', 20);

            $table->index(['key_id'], 'key_issues_key_idx');
            $table->index(['school_id', 'status', 'due_back_on'], 'key_issues_status_due_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('key_issues');
    }
};
