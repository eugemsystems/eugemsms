<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\Document;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Support\FinanceDocumentTemplates as T;
use Modules\Finance\Models\Invoice;
use Modules\People\Models\Guardian;

/**
 * ACT-RenderInvoiceDocument (Book B FIN-03 BR-FIN-03-021). The printable
 * invoice, rendered once from the template in force when it was issued and kept;
 * asking again returns that same document (an issued invoice is never edited).
 */
final class RenderInvoiceDocumentAction extends Action
{
    protected bool $transactional = false;

    public function __construct(private readonly RenderFinanceDocumentAction $render) {}

    public function execute(Invoice $invoice, int $generatedByUserId): Document
    {
        $existing = Document::query()->where('document_type', T::INVOICE)->where('documentable_type', $invoice->getMorphClass())->where('documentable_id', $invoice->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        // The event can fire with the in-memory model: reload so database defaults (paid, balance) are present.
        $invoice = $invoice->fresh(['lines', 'student']) ?? $invoice;
        $party = $invoice->billed_party_type === 'guardian' ? Guardian::query()->find($invoice->billed_party_id) : null;

        return $this->render->execute($invoice->school_id, T::INVOICE, [
            'school' => ['name' => (string) School::findOrFail($invoice->school_id)->name],
            'invoice' => [
                'number' => $invoice->invoice_number, 'issue_date' => $invoice->issue_date->toDateString(), 'due_date' => $invoice->due_date->toDateString(), 'currency' => $invoice->currency,
                'gross' => T::money($invoice->gross_minor), 'discount' => T::money($invoice->discount_minor), 'net' => T::money($invoice->net_minor),
                'paid' => T::money($invoice->paid_minor), 'balance' => T::money($invoice->balance_minor), 'status' => $invoice->status,
            ],
            'student' => ['name' => (string) $invoice->student?->fullName(), 'admission_number' => (string) $invoice->student?->admission_number],
            'billed_to' => $party?->displayName() ?? '',
            'lines' => $invoice->lines->sortBy('line_number')->map(fn ($l): array => ['description' => $l->description, 'gross' => T::money($l->gross_minor), 'discount' => T::money($l->discount_minor), 'net' => T::money($l->net_minor)])->values()->all(),
        ], $generatedByUserId, $invoice->academic_year_id, $invoice->term_id, $invoice->getMorphClass(), $invoice->id);
    }
}
