<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Intelligence\Livewire\EarlyWarning\Enrolment as EarlyWarningEnrolment;
use Modules\Intelligence\Livewire\EarlyWarning\FeeRisk as EarlyWarningFeeRisk;
use Modules\Intelligence\Livewire\EarlyWarning\Queue as EarlyWarningQueue;
use Modules\Intelligence\Livewire\EarlyWarning\StaffWellbeing as EarlyWarningStaffWellbeing;
use Modules\Intelligence\Livewire\EarlyWarning\StudentDetail as EarlyWarningStudentDetail;
use Modules\Intelligence\Livewire\EarlyWarning\Weights as EarlyWarningWeights;
use Modules\Intelligence\Livewire\Executive\BoardPack as ExecutiveBoardPack;
use Modules\Intelligence\Livewire\Executive\BursarDashboard as ExecutiveBursarDashboard;
use Modules\Intelligence\Livewire\Executive\HeadDashboard as ExecutiveHeadDashboard;
use Modules\Intelligence\Livewire\Executive\Kpis as ExecutiveKpis;
use Modules\Intelligence\Livewire\Insights\Reports\Builder as ReportBuilder;
use Modules\Intelligence\Livewire\Insights\Reports\ExecutionLog as ReportExecutionLog;
use Modules\Intelligence\Livewire\Insights\Reports\Index as ReportsIndex;
use Modules\Intelligence\Livewire\Insights\Reports\Schedule as ReportSchedule;
use Modules\Intelligence\Livewire\Insights\Reports\Shared as ReportsShared;

/**
 * Book J admin screens, school-scoped like every other module's own
 * route group. Everything here is school-facing — the vendor-facing SAA
 * modules must never share a route group, guard or permission with it
 * (Book J §0.2).
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/insights')->name('insights.')->group(function (): void {
    Route::prefix('reports')->name('reports.')->group(function (): void {
        Route::livewire('/', ReportsIndex::class)->name('index');
        Route::livewire('build', ReportBuilder::class)->name('builder');
        Route::livewire('shared', ReportsShared::class)->name('shared');
        Route::livewire('schedule', ReportSchedule::class)->name('schedule');
        Route::livewire('executions', ReportExecutionLog::class)->name('executions');
    });

    Route::prefix('executive')->name('executive.')->group(function (): void {
        Route::livewire('head', ExecutiveHeadDashboard::class)->name('head');
        Route::livewire('bursar', ExecutiveBursarDashboard::class)->name('bursar');
        Route::livewire('kpis', ExecutiveKpis::class)->name('kpis');
        Route::livewire('board-pack', ExecutiveBoardPack::class)->name('board-pack');
    });

    Route::prefix('early-warning')->name('early-warning.')->group(function (): void {
        Route::livewire('queue', EarlyWarningQueue::class)->name('queue');
        Route::livewire('students/{student}', EarlyWarningStudentDetail::class)->whereNumber('student')->name('student');
        Route::livewire('fee-risk', EarlyWarningFeeRisk::class)->name('fee-risk');
        Route::livewire('enrolment', EarlyWarningEnrolment::class)->name('enrolment');
        Route::livewire('staff-wellbeing', EarlyWarningStaffWellbeing::class)->name('staff-wellbeing');
        Route::livewire('weights', EarlyWarningWeights::class)->name('weights');
    });
});
