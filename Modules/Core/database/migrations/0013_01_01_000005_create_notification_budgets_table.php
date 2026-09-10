<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_budgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->char('period_month', 7);
            $table->string('channel', 20);
            $table->bigInteger('cap_minor')->nullable();
            $table->bigInteger('spent_minor')->default(0);
            $table->char('currency', 3);
            $table->tinyInteger('warn_at_percent')->unsigned()->default(80);
            $table->boolean('is_hard_stop')->default(true);

            $table->unique(['school_id', 'period_month', 'channel'], 'notification_budgets_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_budgets');
    }
};
