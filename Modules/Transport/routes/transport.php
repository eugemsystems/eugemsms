<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Transport\Livewire\Assignment\Index as AssignmentIndex;
use Modules\Transport\Livewire\Compliance\Index as ComplianceIndex;
use Modules\Transport\Livewire\Drivers\Index as DriversIndex;
use Modules\Transport\Livewire\Fleet\Index as FleetIndex;
use Modules\Transport\Livewire\Fuel\Index as FuelIndex;
use Modules\Transport\Livewire\FuelAnomalies\Index as FuelAnomaliesIndex;
use Modules\Transport\Livewire\Incidents\Index as IncidentsIndex;
use Modules\Transport\Livewire\Manifest\Show as ManifestShow;
use Modules\Transport\Livewire\RouteCosts\Index as RouteCostsIndex;
use Modules\Transport\Livewire\Routes\Index as RoutesIndex;
use Modules\Transport\Livewire\Trips\Index as TripsIndex;

/**
 * Book H2 OPS-01 §5 — Transport & Fleet Management admin screens.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/transport')->name('transport.')->group(function (): void {
    Route::livewire('fleet', FleetIndex::class)->name('fleet.index');
    Route::livewire('compliance', ComplianceIndex::class)->name('compliance.index');
    Route::livewire('drivers', DriversIndex::class)->name('drivers.index');
    Route::livewire('routes', RoutesIndex::class)->name('routes.index');
    Route::livewire('assignment', AssignmentIndex::class)->name('assignment.index');
    Route::livewire('trips', TripsIndex::class)->name('trips.index');
    Route::livewire('manifest', ManifestShow::class)->name('manifest.show');
    Route::livewire('fuel', FuelIndex::class)->name('fuel.index');
    Route::livewire('fuel-anomalies', FuelAnomaliesIndex::class)->name('fuel-anomalies.index');
    Route::livewire('incidents', IncidentsIndex::class)->name('incidents.index');
    Route::livewire('route-costs', RouteCostsIndex::class)->name('route-costs.index');
});
