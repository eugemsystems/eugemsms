<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-04 §2. Deliberately no `school_id`/`BelongsToSchool`: a
 * row here can be scoped to *any* level of `ScopeChain` (tenant, school,
 * section, academic_year, term, user), which `scope_type`/`scope_id`
 * already identify precisely — an additional school-scope column and
 * global scope would fight that, not reinforce it, for the
 * tenant/user-scoped rows in particular.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setting_values', function (Blueprint $table): void {
            $table->id();
            $table->string('setting_key', 120)->index();
            $table->string('scope_type', 20);
            $table->unsignedBigInteger('scope_id');
            $table->text('value')->nullable();
            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['setting_key', 'scope_type', 'scope_id']);
            $table->index(['scope_type', 'scope_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setting_values');
    }
};
