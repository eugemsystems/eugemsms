<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Comms\Livewire\Messaging\Gateways\Index as GatewaysIndex;
use Modules\Comms\Livewire\Messaging\Gateways\Webhooks as GatewayWebhooks;
use Modules\Comms\Livewire\Messaging\Reports\Cost as CostReport;
use Modules\Comms\Livewire\Messaging\Reports\Reconciliation as ReconciliationReport;
use Modules\Comms\Livewire\Messaging\Sms\SenderIds;
use Modules\Comms\Livewire\Messaging\WhatsApp\Templates as WhatsAppTemplates;

/**
 * Book I admin screens, school-scoped like every other module's own
 * route group in this codebase.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/comms')->name('comms.')->group(function (): void {
    Route::livewire('gateways', GatewaysIndex::class)->name('gateways.index');
    Route::livewire('gateways/webhooks', GatewayWebhooks::class)->name('gateways.webhooks');
    Route::livewire('whatsapp/templates', WhatsAppTemplates::class)->name('whatsapp.templates');
    Route::livewire('sms/sender-ids', SenderIds::class)->name('sms.sender-ids');
    Route::livewire('reports/cost', CostReport::class)->name('reports.cost');
    Route::livewire('reports/reconciliation', ReconciliationReport::class)->name('reports.reconciliation');
});
