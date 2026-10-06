<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Stores\Livewire\Procurement\Contracts\Index as ContractsIndex;
use Modules\Stores\Livewire\Procurement\Invoices\MatchReview;
use Modules\Stores\Livewire\Procurement\Invoices\Register as InvoiceRegister;
use Modules\Stores\Livewire\Procurement\Orders\Index as OrdersIndex;
use Modules\Stores\Livewire\Procurement\Payments\Run as PaymentsRun;
use Modules\Stores\Livewire\Procurement\Quotations\Compare;
use Modules\Stores\Livewire\Procurement\Receipts\Create as GrnCreate;
use Modules\Stores\Livewire\Procurement\Reports\Index as ProcurementReports;
use Modules\Stores\Livewire\Procurement\Requisitions\Index as PurchaseRequisitionsIndex;
use Modules\Stores\Livewire\Procurement\Suppliers\BankChange;
use Modules\Stores\Livewire\Procurement\Suppliers\Clearances;
use Modules\Stores\Livewire\Procurement\Suppliers\Index as SuppliersIndex;
use Modules\Stores\Livewire\Procurement\Suppliers\Show as SupplierShow;

/**
 * Book H1 FIN-08 §7 🇿🇼 — Procurement, Suppliers & Accounts Payable
 * admin screens.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/stores/procurement')->name('stores.procurement.')->group(function (): void {
    Route::livewire('suppliers', SuppliersIndex::class)->name('suppliers.index');
    Route::livewire('suppliers/{supplier}', SupplierShow::class)->name('suppliers.show');
    Route::livewire('suppliers/{supplier}/bank-change', BankChange::class)->name('suppliers.bank-change');
    Route::livewire('contracts', ContractsIndex::class)->name('contracts.index');
    Route::livewire('suppliers-clearances', Clearances::class)->name('suppliers.clearances');
    Route::livewire('requisitions', PurchaseRequisitionsIndex::class)->name('requisitions.index');
    Route::livewire('quotations', Compare::class)->name('quotations.compare');
    Route::livewire('orders', OrdersIndex::class)->name('orders.index');
    Route::livewire('receipts/create', GrnCreate::class)->name('receipts.create');
    Route::livewire('invoices/register', InvoiceRegister::class)->name('invoices.register');
    Route::livewire('invoices/match', MatchReview::class)->name('invoices.match');
    Route::livewire('payments/run', PaymentsRun::class)->name('payments.run');
    Route::livewire('reports', ProcurementReports::class)->name('reports.index');
});
