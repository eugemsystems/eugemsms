<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Finance\Livewire\Accounts\Editor as AccountEditor;
use Modules\Finance\Livewire\Accounts\Ledger as AccountLedger;
use Modules\Finance\Livewire\Accounts\Tree as AccountTree;
use Modules\Finance\Livewire\CostCentres\Index as CostCentresIndex;
use Modules\Finance\Livewire\Integrity\Balances as IntegrityBalances;
use Modules\Finance\Livewire\Journals\Create as JournalCreate;
use Modules\Finance\Livewire\Journals\Index as JournalIndex;
use Modules\Finance\Livewire\Journals\Reverse as JournalReverse;
use Modules\Finance\Livewire\Journals\Show as JournalShow;
use Modules\Finance\Livewire\PostingRules\Index as PostingRulesIndex;
use Modules\Finance\Livewire\Reports\TrialBalance as TrialBalanceReport;

/**
 * Book B FIN-01 §8 — General Ledger admin screens. Every route is
 * school-scoped (`{school}`), matching `InteractsWithSchool`'s own
 * `{school}` route-parameter convention used throughout Book A.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/finance')->name('finance.')->group(function (): void {
    Route::livewire('accounts', AccountTree::class)->name('accounts.tree');
    Route::livewire('accounts/create', AccountEditor::class)->name('accounts.create');
    Route::livewire('accounts/{account}/edit', AccountEditor::class)->name('accounts.edit');
    Route::livewire('accounts/{account}/ledger', AccountLedger::class)->name('accounts.ledger');

    Route::livewire('cost-centres', CostCentresIndex::class)->name('cost-centres.index');

    Route::livewire('journals', JournalIndex::class)->name('journals.index');
    Route::livewire('journals/create', JournalCreate::class)->name('journals.create');
    Route::livewire('journals/{journal}', JournalShow::class)->name('journals.show');
    Route::livewire('journals/{journal}/reverse', JournalReverse::class)->name('journals.reverse');

    Route::livewire('posting-rules', PostingRulesIndex::class)->name('posting-rules.index');

    Route::livewire('reports/trial-balance', TrialBalanceReport::class)->name('reports.trial-balance');

    Route::livewire('integrity/balances', IntegrityBalances::class)->name('integrity.balances');
});
