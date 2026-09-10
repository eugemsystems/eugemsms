<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 FIN-14 §2. `store_id` (`FIN-09`) and `till_id` (`FIN-04`)
 * are both real FKs — both modules already exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spend_points', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 120);
            $table->string('point_type', 30);
            $table->foreignId('store_id')->nullable()->constrained('stores');
            $table->foreignId('till_id')->nullable()->constrained('tills');
            $table->foreignId('income_account_id')->constrained('accounts');
            $table->foreignId('cost_centre_id')->constrained('cost_centres');
            $table->boolean('is_fiscalisable')->default(true);
            $table->json('operating_hours')->nullable();
            $table->boolean('is_active')->default(true);

            $table->unique(['school_id', 'code'], 'spend_points_school_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spend_points');
    }
};
