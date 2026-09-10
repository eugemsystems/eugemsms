<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book I COM-08 §2 ⭐/BR-COM-08-005. Append-only — see
 * `Modules\Comms\Models\ComplaintUpdate::booted()`. `visible_to_raiser`
 * is set once at creation and never changed after.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaint_updates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('complaint_id')->constrained()->cascadeOnDelete();
            $table->string('update_type', 20);
            $table->text('content')->nullable();
            $table->boolean('visible_to_raiser')->default(true);
            $table->foreignId('posted_by')->constrained('users');
            $table->timestamp('posted_at');

            $table->index(['school_id', 'complaint_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_updates');
    }
};
