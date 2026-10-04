<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Finance\Livewire\Bank\Accounts as BankAccounts;
use Modules\Finance\Livewire\Bank\Import as BankImport;
use Modules\Finance\Livewire\Bank\Matching as BankMatching;
use Modules\Finance\Livewire\Gateways\Index as GatewaysIndex;
use Modules\Finance\Livewire\Gateways\Intents as GatewayIntents;
use Modules\Finance\Livewire\Gateways\Webhooks as GatewayWebhooks;
use Modules\Finance\Livewire\Reconciliation\Dashboard as ReconciliationDashboard;
use Modules\Finance\Livewire\Reconciliation\Exceptions as ReconciliationExceptions;

/**
 * Book B FIN-05 §8 — Payment Gateways & Reconciliation admin screens.
 * School-scoped (`{school}`), matching `till.php`'s own convention.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/finance')->name('finance.')->group(function (): void {
    Route::livewire('gateways', GatewaysIndex::class)->name('gateways.index');
    Route::livewire('gateways/intents', GatewayIntents::class)->name('gateways.intents');
    Route::livewire('gateways/webhooks', GatewayWebhooks::class)->name('gateways.webhooks');

    Route::livewire('bank/accounts', BankAccounts::class)->name('bank.accounts');
    Route::livewire('bank/import', BankImport::class)->name('bank.import');
    Route::livewire('bank/{statement}/matching', BankMatching::class)->name('bank.matching');

    Route::livewire('reconciliation/dashboard', ReconciliationDashboard::class)->name('reconciliation.dashboard');
    Route::livewire('reconciliation/exceptions', ReconciliationExceptions::class)->name('reconciliation.exceptions');
});
