<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Livewire\CustomFields\Builder as CustomFieldsBuilder;
use Modules\Core\Livewire\CustomFields\Index as CustomFieldsIndex;
use Modules\Core\Livewire\FeatureFlags\Index as FeatureFlagsIndex;
use Modules\Core\Livewire\Profiles\Index as ProfilesIndex;
use Modules\Core\Livewire\Settings\Edit as SettingsEdit;
use Modules\Core\Livewire\Settings\History as SettingsHistory;
use Modules\Core\Livewire\Settings\Index as SettingsIndex;

/**
 * Book A CORE-04 §5/§6 — Settings, Feature Flags & Custom Fields. Every
 * school-scoped screen takes an explicit `{school}` and authorises +
 * sets SchoolContext itself (`InteractsWithSchool`), matching
 * schools.php's convention. `Core\FeatureFlags\Index` is the one
 * exception — feature flags are platform-wide, not per-school data, so
 * it takes no `{school}` (the spec marks its permission "(vendor)";
 * CORE-05 hasn't shipped a permission system to gate that yet, same gap
 * as everywhere else in this build).
 */
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('schools/{school}/settings', SettingsIndex::class)->name('settings.index');
    Route::livewire('schools/{school}/settings/history', SettingsHistory::class)->name('settings.history');
    Route::livewire('schools/{school}/settings/profiles', ProfilesIndex::class)->name('settings.profiles');
    Route::livewire('schools/{school}/settings/edit/{key}', SettingsEdit::class)->where('key', '.*')->name('settings.edit');
    Route::livewire('schools/{school}/custom-fields', CustomFieldsIndex::class)->name('custom-fields.index');
    Route::livewire('schools/{school}/custom-fields/create', CustomFieldsBuilder::class)->name('custom-fields.create');
    Route::livewire('settings/feature-flags', FeatureFlagsIndex::class)->name('feature-flags.index');
});
