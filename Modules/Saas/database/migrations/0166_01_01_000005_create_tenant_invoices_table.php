<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J SAA-01 §2 ⭐/BR-SAA-01-001 ⭐ — the VENDOR's AR, structurally
 * separate from any school's `FIN-01` ledger. A school never queries
 * this table directly; `GetMySubscriptionAction` is the only narrow
 * read path onto it (§5/AC-SAA-01-006).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_invoices', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained();
            $table->string('invoice_number', 40);
            $table->char('period_month', 7);
            $table->json('line_items');
            $table->bigInteger('subtotal_minor');
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('total_minor');
            $table->char('currency', 3);
            $table->date('due_date');
            $table->string('status', 20);
            $table->timestamps();

            $table->unique(['tenant_id', 'invoice_number']);
            $table->index(['tenant_id', 'status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_invoices');
    }
};
