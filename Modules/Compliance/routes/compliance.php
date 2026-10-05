<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Compliance\Livewire\Mopse\InspectionPack\Index as MopseInspectionPackIndex;
use Modules\Compliance\Livewire\Mopse\SchoolReturns\Index as MopseSchoolReturnsIndex;
use Modules\Compliance\Livewire\Policy\IncidentRegister\Index as PolicyIncidentRegisterIndex;
use Modules\Compliance\Livewire\Policy\Minutes\Index as PolicyMinutesIndex;
use Modules\Compliance\Livewire\Policy\Policies\Index as PolicyPoliciesIndex;
use Modules\Compliance\Livewire\Policy\StatutoryDocuments\Index as PolicyStatutoryDocumentsIndex;
use Modules\Compliance\Livewire\Privacy\Breaches\Index as PrivacyBreachesIndex;
use Modules\Compliance\Livewire\Privacy\Consents\Index as PrivacyConsentsIndex;
use Modules\Compliance\Livewire\Privacy\ConsentTypes\Index as PrivacyConsentTypesIndex;
use Modules\Compliance\Livewire\Privacy\Disposal\Index as PrivacyDisposalIndex;
use Modules\Compliance\Livewire\Privacy\Notices\Index as PrivacyNoticesIndex;
use Modules\Compliance\Livewire\Privacy\Processing\Index as PrivacyProcessingIndex;
use Modules\Compliance\Livewire\Privacy\Processors\Index as PrivacyProcessorsIndex;
use Modules\Compliance\Livewire\Privacy\Requests\Index as PrivacyRequestsIndex;
use Modules\Compliance\Livewire\Privacy\Retention\Index as PrivacyRetentionIndex;
use Modules\Compliance\Livewire\Zimsec\Analysis\Index as ZimsecAnalysisIndex;
use Modules\Compliance\Livewire\Zimsec\Export\Index as ZimsecExportIndex;
use Modules\Compliance\Livewire\Zimsec\Fees\Index as ZimsecFeesIndex;
use Modules\Compliance\Livewire\Zimsec\Registrations\Index as ZimsecRegistrationsIndex;
use Modules\Compliance\Livewire\Zimsec\ResultsImport\Index as ZimsecResultsImportIndex;
use Modules\Compliance\Livewire\Zimsec\Statements\Index as ZimsecStatementsIndex;
use Modules\Compliance\Livewire\Zimsec\Validation\Index as ZimsecValidationIndex;

/**
 * Book H3 CMP-01 to CMP-04 admin screens, school-scoped like every
 * other module's own route group in this codebase.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/compliance')->name('compliance.')->group(function (): void {
    Route::prefix('zimsec')->name('zimsec.')->group(function (): void {
        Route::livewire('registrations', ZimsecRegistrationsIndex::class)->name('registrations.index');
        Route::livewire('validation', ZimsecValidationIndex::class)->name('validation.index');
        Route::livewire('fees', ZimsecFeesIndex::class)->name('fees.index');
        Route::livewire('export', ZimsecExportIndex::class)->name('export.index');
        Route::livewire('statements', ZimsecStatementsIndex::class)->name('statements.index');
        Route::livewire('results-import', ZimsecResultsImportIndex::class)->name('results-import.index');
        Route::livewire('analysis', ZimsecAnalysisIndex::class)->name('analysis.index');
    });

    Route::prefix('mopse')->name('mopse.')->group(function (): void {
        Route::livewire('returns', MopseSchoolReturnsIndex::class)->name('returns.index');
        Route::livewire('inspection-pack', MopseInspectionPackIndex::class)->name('inspection-pack.index');
    });

    Route::prefix('privacy')->name('privacy.')->group(function (): void {
        Route::livewire('consent-types', PrivacyConsentTypesIndex::class)->name('consent-types.index');
        Route::livewire('consents', PrivacyConsentsIndex::class)->name('consents.index');
        Route::livewire('retention', PrivacyRetentionIndex::class)->name('retention.index');
        Route::livewire('disposal', PrivacyDisposalIndex::class)->name('disposal.index');
        Route::livewire('requests', PrivacyRequestsIndex::class)->name('requests.index');
        Route::livewire('breaches', PrivacyBreachesIndex::class)->name('breaches.index');
        Route::livewire('processing', PrivacyProcessingIndex::class)->name('processing.index');
        Route::livewire('processors', PrivacyProcessorsIndex::class)->name('processors.index');
        Route::livewire('notices', PrivacyNoticesIndex::class)->name('notices.index');
    });

    Route::prefix('policy')->name('policy.')->group(function (): void {
        Route::livewire('policies', PolicyPoliciesIndex::class)->name('policies.index');
        Route::livewire('documents', PolicyStatutoryDocumentsIndex::class)->name('documents.index');
        Route::livewire('minutes', PolicyMinutesIndex::class)->name('minutes.index');
        Route::livewire('incident-register', PolicyIncidentRegisterIndex::class)->name('incident-register.index');
    });
});
