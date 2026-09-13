<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Finance\Livewire\Accounts\LearnerAccount;
use Modules\Finance\Livewire\CreditNotes\Create as CreditNoteCreate;
use Modules\Finance\Livewire\Debtors\Workbench;
use Modules\Finance\Livewire\Invoices\Index as InvoicesIndex;
use Modules\Finance\Livewire\Invoices\Show as InvoiceShow;
use Modules\Finance\Livewire\Invoices\VoidInvoice;
use Modules\Finance\Livewire\Liabilities\Editor as LiabilitiesEditor;
use Modules\Finance\Livewire\PaymentPlans\Index as PaymentPlansIndex;
use Modules\Finance\Livewire\Reminders\Schedules as ReminderSchedules;
use Modules\Finance\Livewire\Reports\AgedDebtors;
use Modules\Finance\Livewire\Statements\Generate as StatementGenerate;
use Modules\Finance\Livewire\Waivers\Index as WaiversIndex;

/**
 * Book B FIN-03 §5 — Invoicing, Statements & Debtor Management admin
 * screens. Every route is school-scoped (`{school}`), matching
 * `ledger.php`'s own convention.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/finance')->name('finance.')->group(function (): void {
    Route::livewire('invoices', InvoicesIndex::class)->name('invoices.index');
    Route::livewire('invoices/{invoice}', InvoiceShow::class)->name('invoices.show');
    Route::livewire('invoices/{invoice}/void', VoidInvoice::class)->name('invoices.void');

    Route::livewire('credit-notes/create', CreditNoteCreate::class)->name('credit-notes.create');

    Route::livewire('learners/{student}/account', LearnerAccount::class)->name('accounts.learner-account');
    Route::livewire('learners/{student}/liabilities', LiabilitiesEditor::class)->name('liabilities.editor');

    Route::livewire('statements/generate', StatementGenerate::class)->name('statements.generate');
    Route::livewire('reports/aged-debtors', AgedDebtors::class)->name('reports.aged-debtors');
    Route::livewire('debtors/workbench', Workbench::class)->name('debtors.workbench');
    Route::livewire('reminders/schedules', ReminderSchedules::class)->name('reminders.schedules');
    Route::livewire('payment-plans', PaymentPlansIndex::class)->name('payment-plans.index');
    Route::livewire('waivers', WaiversIndex::class)->name('waivers.index');
});
