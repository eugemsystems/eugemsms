<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('depreciation_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('run_id')->constrained('depreciation_runs');
            $table->foreignId('asset_id')->constrained('fixed_assets');
            $table->bigInteger('opening_nbv_minor');
            $table->bigInteger('depreciation_minor');
            $table->bigInteger('closing_nbv_minor');
            $table->string('method_used', 30);
            $table->string('calculation_note', 255)->nullable();

            $table->unique(['run_id', 'asset_id']);
            $table->index(['asset_id'], 'depreciation_entries_asset_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depreciation_entries');
    }
};
