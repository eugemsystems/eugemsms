<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_labour', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_order_id')->constrained('work_orders');
            $table->foreignId('staff_id')->constrained('staff');
            $table->date('work_date');
            $table->decimal('hours', 5, 2);
            $table->bigInteger('hourly_rate_minor')->nullable();
            $table->bigInteger('cost_minor')->nullable();
            $table->string('notes', 255)->nullable();

            $table->index(['work_order_id'], 'work_order_labour_wo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_labour');
    }
};
