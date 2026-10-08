<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Operations\Livewire\Maintenance\Assets\Index as AssetsIndex;
use Modules\Operations\Livewire\Maintenance\Contractors\Index as ContractorsIndex;
use Modules\Operations\Livewire\Maintenance\Report as MaintenanceReport;
use Modules\Operations\Livewire\Maintenance\Reports\Index as ReportsIndex;
use Modules\Operations\Livewire\Maintenance\Schedules\Index as SchedulesIndex;
use Modules\Operations\Livewire\Maintenance\Triage as MaintenanceTriage;
use Modules\Operations\Livewire\Maintenance\WorkOrders\Index as WorkOrdersIndex;
use Modules\Operations\Livewire\Projects\Index as ProjectsIndex;

/**
 * Book H2 OPS-02 §7 — Maintenance & Works Management admin screens.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/operations/maintenance')->name('operations.maintenance.')->group(function (): void {
    Route::livewire('report', MaintenanceReport::class)->name('report');
    Route::livewire('triage', MaintenanceTriage::class)->name('triage');
    Route::livewire('work-orders', WorkOrdersIndex::class)->name('work-orders.index');
    Route::livewire('assets', AssetsIndex::class)->name('assets.index');
    Route::livewire('schedules', SchedulesIndex::class)->name('schedules.index');
    Route::livewire('reports', ReportsIndex::class)->name('reports.index');
    Route::livewire('contractors', ContractorsIndex::class)->name('contractors.index');
});

Route::middleware(['auth', 'verified'])->prefix('schools/{school}/operations/projects')->name('operations.projects.')->group(function (): void {
    Route::livewire('index', ProjectsIndex::class)->name('index');
});
