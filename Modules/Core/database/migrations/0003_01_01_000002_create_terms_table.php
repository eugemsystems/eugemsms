<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terms', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('number')->unsigned();
            $table->string('name', 40);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_current')->default(false);
            $table->string('academic_state', 20)->default('planned');
            $table->string('financial_state', 20)->default('planned');
            $table->timestamp('academic_closed_at')->nullable();
            $table->foreignId('academic_closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('financial_closed_at')->nullable();
            $table->foreignId('financial_closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'academic_year_id', 'number']);
            $table->index(['school_id', 'is_current']);
            $table->index(['school_id', 'starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terms');
    }
};
