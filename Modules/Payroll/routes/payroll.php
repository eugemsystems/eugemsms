<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Payroll\Livewire\Components\Index as ComponentsIndex;
use Modules\Payroll\Livewire\Grades\Index as GradesIndex;
use Modules\Payroll\Livewire\Loans\Index as LoansIndex;
use Modules\Payroll\Livewire\Payslips\Show as PayslipsShow;
use Modules\Payroll\Livewire\Reports\Summary as ReportsSummary;
use Modules\Payroll\Livewire\Returns\Index as ReturnsIndex;
use Modules\Payroll\Livewire\Returns\Itf16 as ReturnsItf16;
use Modules\Payroll\Livewire\Run\BankFile as RunBankFile;
use Modules\Payroll\Livewire\Run\Wizard as RunWizard;
use Modules\Payroll\Livewire\Staff\Structure as StaffStructure;
use Modules\Payroll\Livewire\Statutory\Config as StatutoryConfig;

/**
 * Book H3 PPL-05 §6 — Payroll & Statutory Deductions admin screens.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/payroll')->name('payroll.')->group(function (): void {
    Route::livewire('statutory', StatutoryConfig::class)->name('statutory.index');
    Route::livewire('grades', GradesIndex::class)->name('grades.index');
    Route::livewire('components', ComponentsIndex::class)->name('components.index');
    Route::livewire('staff-structure', StaffStructure::class)->name('staff.structure');
    Route::livewire('loans', LoansIndex::class)->name('loans.index');
    Route::livewire('run', RunWizard::class)->name('run.wizard');
    Route::livewire('run/bank-file', RunBankFile::class)->name('run.bank-file');
    Route::livewire('payslips/{payslip}', PayslipsShow::class)->name('payslips.show');
    Route::livewire('returns', ReturnsIndex::class)->name('returns.index');
    Route::livewire('returns/itf16', ReturnsItf16::class)->name('returns.itf16');
    Route::livewire('reports', ReportsSummary::class)->name('reports.summary');
});
