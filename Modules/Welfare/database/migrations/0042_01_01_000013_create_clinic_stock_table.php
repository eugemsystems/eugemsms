<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book G BRD-06 §2 — lightweight; full stock mechanics are `FIN-09`
 * (Book H, not built). `inventory_item_id` is a forward reference.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_stock', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('inventory_item_id')->nullable();
            $table->string('name', 150);
            $table->string('category', 30);
            $table->boolean('is_controlled')->default(false);
            $table->decimal('quantity_on_hand', 10, 2)->default(0);
            $table->string('unit', 20);
            $table->decimal('reorder_level', 10, 2)->nullable();
            $table->string('batch_number', 60)->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('storage_location', 150)->nullable();
            $table->timestamps();

            $table->index(['school_id', 'expiry_date']);
            $table->index(['school_id', 'is_controlled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_stock');
    }
};
