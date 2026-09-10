<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrity_check_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->string('check_type', 50);
            $table->string('status', 20);
            $table->bigInteger('records_checked')->nullable();
            $table->integer('failures_found')->default(0);
            $table->json('failure_details')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->timestamp('ran_at');

            $table->index(['check_type', 'status', 'ran_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrity_check_runs');
    }
};
