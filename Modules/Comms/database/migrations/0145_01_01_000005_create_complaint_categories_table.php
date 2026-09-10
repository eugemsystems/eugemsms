<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book I COM-08 §2 ⭐/BR-COM-08-006. `is_safeguarding_trigger` is one
 * column beyond the spec's literal schema — the exact field name and
 * semantic already established on `Modules\Welfare\Models\BehaviourCategory`
 * (Book G BRD-07 §2, `BR-BRD-07-018`). Research confirmed that rule is
 * a pre-flagged-category match, NOT free-text scanning of a
 * description — there is no real, safe text-detection mechanism
 * anywhere in this codebase to mirror instead, and inventing a
 * keyword scanner for a safeguarding-critical routing decision would
 * be worse than being honest that this, like BRD-07, is category-
 * level and (per `RaiseComplaintAction`) staff-assertable at intake.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaint_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 120);
            $table->unsignedBigInteger('default_assignee_role_id')->nullable();
            $table->integer('sla_hours')->default(72);
            $table->boolean('is_safeguarding_trigger')->default(false);
            $table->timestamps();

            $table->unique(['school_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_categories');
    }
};
