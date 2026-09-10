<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book I COM-02 §2/BR-COM-02-009/010.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rule_template_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rule_id')->constrained('automation_rules')->cascadeOnDelete();
            $table->string('variant_key', 20);
            $table->string('template_key', 80);
            $table->smallInteger('weight_percent')->default(50);
            $table->integer('sent_count')->default(0);
            $table->integer('opened_count')->default(0);
            $table->integer('response_count')->default(0);
            $table->timestamps();

            $table->unique(['rule_id', 'variant_key'], 'rule_template_variants_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rule_template_variants');
    }
};
