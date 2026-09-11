<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book K ACA-10 §2/BR-ACA-10-010. This migration omits the spec's
 * `purchase_requisition_id` FK to `FIN-08` (procurement), which this
 * codebase has not built yet — see this module's build notes. Once
 * `FIN-08` exists, an approved request's routing into that pipeline is
 * the point to add it back and wire the transition past `requested`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acquisition_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('requested_title', 300);
            $table->foreignId('requested_by')->constrained('users');
            $table->smallInteger('copies_requested')->default(1);
            $table->unsignedBigInteger('estimated_cost_minor')->nullable();
            $table->string('status', 20);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acquisition_requests');
    }
};
