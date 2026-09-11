<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book K PPL-06 §2/BR-PPL-06-006. A stated intention, not a
 * receivable the school's books recognise as certain income —
 * `paid_to_date_minor` is only ever moved by `RecordDonationAction`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pledges', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('capital_campaigns');
            $table->foreignId('alumnus_id')->nullable()->constrained('alumni');
            $table->string('donor_name', 200);
            $table->string('donor_type', 20);
            $table->unsignedBigInteger('pledged_amount_minor');
            $table->char('currency', 3);
            $table->json('schedule')->nullable();
            $table->unsignedBigInteger('paid_to_date_minor')->default(0);
            $table->string('recognition_tier', 30)->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->string('status', 20);
            $table->timestamps();

            $table->index(['school_id', 'campaign_id', 'status'], 'pledges_campaign_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pledges');
    }
};
