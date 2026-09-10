<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_disposals', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->unique()->constrained('fixed_assets');
            $table->date('disposal_date');
            $table->string('disposal_method', 30);
            $table->bigInteger('proceeds_minor')->default(0);
            $table->string('currency', 3);
            $table->bigInteger('nbv_at_disposal_minor');
            $table->bigInteger('gain_loss_minor');
            $table->string('buyer', 200)->nullable();
            $table->text('reason');
            $table->unsignedBigInteger('approval_request_id')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->foreignId('journal_id')->nullable()->constrained('journals');
            $table->unsignedBigInteger('document_file_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_disposals');
    }
};
