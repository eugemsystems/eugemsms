<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Listeners;

use Modules\Core\Domain\Actions\Scheduling\ResolveSystemActorAction;
use Modules\Finance\Domain\Actions\RenderInvoiceDocumentAction;
use Modules\Finance\Domain\Actions\RenderReceiptDocumentAction;
use Modules\Finance\Domain\Events\InvoiceIssued;
use Modules\Finance\Domain\Events\ReceiptPosted;
use Throwable;

/**
 * Renders the printable invoice or receipt the moment it is issued (Book B
 * FIN-03 BR-FIN-03-021). A document never blocks the money: if rendering fails
 * the invoice or receipt stands, and the document is made on the next request
 * for it.
 */
final class RenderIssuedDocumentsListener
{
    public function __construct(
        private readonly RenderInvoiceDocumentAction $renderInvoice,
        private readonly RenderReceiptDocumentAction $renderReceipt,
        private readonly ResolveSystemActorAction $systemActor,
    ) {}

    public function onInvoiceIssued(InvoiceIssued $event): void
    {
        try {
            $this->renderInvoice->execute($event->invoice, $this->systemActor->execute());
        } catch (Throwable) {
            // Documents never block the operation they attach to.
        }
    }

    public function onReceiptPosted(ReceiptPosted $event): void
    {
        try {
            $this->renderReceipt->execute($event->receipt, $this->systemActor->execute());
        } catch (Throwable) {
            // Documents never block the operation they attach to.
        }
    }
}
