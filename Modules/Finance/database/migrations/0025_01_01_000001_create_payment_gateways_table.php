<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book B FIN-05 §3/BR-FIN-05-016. `credentials` is `text` (not the
 * spec's literal column position note, just noting it's encrypted at
 * rest — Laravel's `encrypted` cast, matching PPL-01's PII pattern)
 * and is never returned by any Action or serialised to an array; see
 * `PaymentGateway::$hidden`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateways', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('driver', 30);
            $table->string('name', 120);
            $table->text('credentials');
            $table->json('supported_methods');
            $table->json('supported_currencies');
            $table->foreignId('settlement_account_id')->constrained('accounts');
            $table->foreignId('fee_account_id')->constrained('accounts');
            $table->json('fee_model')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_sandbox')->default(true);
            $table->boolean('is_active')->default(false);
            $table->smallInteger('priority')->default(0);
            $table->timestamp('last_health_check_at')->nullable();
            $table->string('health_status', 20)->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'driver']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};
