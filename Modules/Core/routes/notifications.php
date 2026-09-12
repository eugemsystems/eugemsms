<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Livewire\Notifications\Budget;
use Modules\Core\Livewire\Notifications\Failures;
use Modules\Core\Livewire\Notifications\Log;
use Modules\Core\Livewire\Notifications\OptOuts;
use Modules\Core\Livewire\Notifications\TemplateEditor;
use Modules\Core\Livewire\Notifications\Templates;

/**
 * Book A CORE-09 §5 — Notification Orchestration Bus. Every screen
 * takes an explicit `{school}` and authorises + sets SchoolContext
 * itself (`InteractsWithSchool`), matching schools.php's/roles.php's
 * own convention.
 */
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('schools/{school}/notifications', Log::class)->name('notifications.log');
    Route::livewire('schools/{school}/notifications/failures', Failures::class)->name('notifications.failures');
    Route::livewire('schools/{school}/notifications/templates', Templates::class)->name('notifications.templates');
    Route::livewire('schools/{school}/notifications/templates/create', TemplateEditor::class)->name('notifications.templates.create');
    Route::livewire('schools/{school}/notifications/budget', Budget::class)->name('notifications.budget');
    Route::livewire('schools/{school}/notifications/opt-outs', OptOuts::class)->name('notifications.opt-outs');
});
