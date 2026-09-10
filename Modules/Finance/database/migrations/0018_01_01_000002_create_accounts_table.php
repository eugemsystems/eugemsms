<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book B FIN-01 §2. The chart of accounts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('account_type_id')->constrained();
            $table->string('code', 20);
            $table->string('name', 150);
            $table->string('description', 255)->nullable();
            $table->boolean('is_postable')->default(true);
            $table->boolean('is_control_account')->default(false);
            $table->string('subledger_type', 30)->nullable();
            $table->boolean('is_system')->default(false);
            $table->string('system_key', 60)->nullable();
            $table->char('currency', 3)->nullable();
            $table->boolean('requires_cost_centre')->default(false);
            $table->boolean('is_active')->default(true);
            $table->date('opened_on')->nullable();
            $table->date('closed_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'code']);
            $table->unique(['school_id', 'system_key']);
            $table->index(['school_id', 'account_type_id', 'is_active']);
            $table->index(['school_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
