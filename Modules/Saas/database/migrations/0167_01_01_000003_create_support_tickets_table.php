<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J SAA-03 §2 ⭐/BR-SAA-03-003 ⭐ — vendor-facing, structurally
 * distinct from `Modules\Comms\Models\Complaint` (`COM-08`): a
 * complaint flows from a school's own parent/staff/learner TO the
 * school; a support ticket flows from a school's user TO the vendor.
 * Not `BelongsToSchool` — the vendor's own queue spans every tenant,
 * the same cross-tenant-visibility shape `SAA-01`'s `tenant_invoices`
 * already established.
 *
 * `subject`/`description` are not in the spec's own §2 column listing
 * but are added here as a documented, necessary extension — the spec
 * is silent on them the way it's silent on obviously-required content
 * columns elsewhere (`COM-08`'s `Complaint` carries the equivalent
 * pair), and "Raise a ticket" (§5) has nothing to raise without them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('raised_by_user_id')->constrained('users');
            $table->string('subject', 200);
            $table->text('description');
            $table->string('category', 40);
            $table->string('priority', 20);
            $table->timestamp('sla_due_at');
            $table->foreignId('assigned_vendor_staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20);
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'sla_due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
