<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Welfare\Livewire\Health\Alerts;
use Modules\Welfare\Livewire\Health\CarePlan;
use Modules\Welfare\Livewire\Health\Consents;
use Modules\Welfare\Livewire\Health\Immunisations;
use Modules\Welfare\Livewire\Health\Incidents;
use Modules\Welfare\Livewire\Health\MedicationRound;
use Modules\Welfare\Livewire\Health\Outbreak;
use Modules\Welfare\Livewire\Health\Prescriptions;
use Modules\Welfare\Livewire\Health\Record;
use Modules\Welfare\Livewire\Health\Referrals;
use Modules\Welfare\Livewire\Health\Screenings;
use Modules\Welfare\Livewire\Health\SickBay;
use Modules\Welfare\Livewire\Health\Stock;

/**
 * Book G BRD-06 §6 — Health, Clinic & Sanatorium admin screens 🔒.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/welfare')->name('welfare.')->group(function (): void {
    Route::livewire('health/students/{student}/record', Record::class)->name('health.record');
    Route::livewire('health/students/{student}/care-plan', CarePlan::class)->name('health.care-plan');
    Route::livewire('health/alerts', Alerts::class)->name('health.alerts');
    Route::livewire('health/sick-bay', SickBay::class)->name('health.sick-bay');
    Route::livewire('health/medication-round', MedicationRound::class)->name('health.medication-round');
    Route::livewire('health/prescriptions', Prescriptions::class)->name('health.prescriptions');
    Route::livewire('health/consents', Consents::class)->name('health.consents');
    Route::livewire('health/immunisations', Immunisations::class)->name('health.immunisations');
    Route::livewire('health/incidents', Incidents::class)->name('health.incidents');
    Route::livewire('health/referrals', Referrals::class)->name('health.referrals');
    Route::livewire('health/stock', Stock::class)->name('health.stock');
    Route::livewire('health/outbreak', Outbreak::class)->name('health.outbreak');
    Route::livewire('health/screenings', Screenings::class)->name('health.screenings');
});
