<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Book H1 FIN-08 §2/BR-FIN-08-001/002. `account_number` is encrypted at
 * rest via the model's own native `'encrypted'` cast — the same
 * pattern `Modules\Finance\Models\PaymentGateway::credentials` and
 * `Modules\People\Models\Student`'s ID-number fields already use in
 * this codebase; `Modules\Core\Domain\Casts\SecondaryEncrypted` is
 * reserved for Book G's clinical/safeguarding tier and deliberately
 * not reused here. `category_ids` is a plain JSON array of
 * `item_categories.id` values, no pivot table — nothing besides
 * display filtering ever queries by it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 200);
            $table->string('trading_name', 200)->nullable();
            $table->string('supplier_type', 30);
            $table->string('bp_number', 30)->nullable();
            $table->string('vat_number', 30)->nullable();
            $table->string('company_registration', 40)->nullable();
            $table->boolean('is_vat_registered')->default(false);
            $table->string('contact_person', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('address_line_1', 200)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country', 2)->default('ZW');
            $table->string('bank_name', 80)->nullable();
            $table->string('bank_branch', 80)->nullable();
            $table->text('account_number')->nullable();
            $table->string('account_name', 200)->nullable();
            $table->string('swift_code', 20)->nullable();
            $table->string('mobile_money_number', 30)->nullable();
            $table->string('preferred_currency', 3);
            $table->smallInteger('payment_terms_days')->default(30);
            $table->bigInteger('credit_limit_minor')->nullable();
            $table->string('credit_limit_currency', 3)->nullable();
            $table->json('category_ids')->nullable();
            $table->foreignId('control_account_id')->nullable()->constrained('accounts');
            $table->decimal('rating', 3, 2)->nullable();
            $table->decimal('on_time_delivery_pct', 5, 2)->nullable();
            $table->decimal('quality_rejection_pct', 5, 2)->nullable();
            $table->timestamp('last_evaluated_at')->nullable();
            $table->string('status', 20);
            $table->text('blacklist_reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_id', 'code']);
            $table->index(['school_id', 'status'], 'suppliers_status_idx');

            if (DB::connection()->getDriverName() !== 'sqlite') {
                $table->fullText(['name', 'trading_name']);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
