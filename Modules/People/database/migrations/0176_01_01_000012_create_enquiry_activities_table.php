<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-02 §2. A call, visit or note against an enquiry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enquiry_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enquiry_id')->constrained()->cascadeOnDelete();
            $table->string('activity_type', 30);
            $table->string('summary', 500);
            $table->string('outcome', 60)->nullable();
            $table->foreignId('performed_by')->constrained('users');
            $table->timestamp('occurred_at');
            $table->index(['school_id', 'enquiry_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiry_activities');
    }
};
