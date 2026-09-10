<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book F BRD-05 §2/BR-BRD-05-005/007.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laundry_cycles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('hostel_id')->constrained('hostels');
            $table->date('cycle_date');
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->smallInteger('items_collected')->default(0);
            $table->smallInteger('items_returned')->default(0);
            $table->smallInteger('items_missing')->default(0);
            $table->string('status', 20);
            $table->bigInteger('cost_minor')->nullable();
            $table->foreignId('supervised_by')->nullable()->constrained('staff');

            $table->unique(['school_id', 'hostel_id', 'cycle_date'], 'laundry_cycles_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laundry_cycles');
    }
};
