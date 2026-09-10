<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book I COM-03 §2/BR-COM-03-004. Per-school enable/reorder — a
 * missing row means "use the widget's own `default_enabled`/
 * `default_sort_order`," never an implicit disable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_widget_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('widget_key', 60);
            $table->string('persona', 20);
            $table->boolean('is_enabled')->default(true);
            $table->smallInteger('sort_order')->nullable();
            $table->timestamps();

            $table->foreign('widget_key')->references('key')->on('dashboard_widgets')->cascadeOnDelete();
            $table->unique(['school_id', 'widget_key', 'persona'], 'school_widget_settings_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_widget_settings');
    }
};
