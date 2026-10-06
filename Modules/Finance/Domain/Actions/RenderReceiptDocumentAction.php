<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\Document;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Support\FinanceDocumentTemplates as T;
use Modules\Finance\Models\Receipt;
use Modules\People\Models\Student;

/**
 * ACT-RenderReceiptDocument (Book B FIN-04). The printable receipt, rendered once
 * and kept; asking again returns the same document.
 */
final class RenderReceiptDocumentAction extends Action
{
    protected bool $transactional = false;

    public function __construct(private readonly RenderFinanceDocumentAction $render) {}

    public function execute(Receipt $receipt, int $generatedByUserId): Document
    {
        $existing = Document::query()->where('document_type', T::RECEIPT)->where('documentable_type', $receipt->getMorphClass())->where('documentable_id', $receipt->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        $receipt = $receipt->fresh(['tenders']) ?? $receipt;
        $student = $receipt->student_id === null ? null : Student::query()->find($receipt->student_id);

        return $this->render->execute($receipt->school_id, T::RECEIPT, [
            'school' => ['name' => (string) School::findOrFail($receipt->school_id)->name],
            'receipt' => [
                'number' => $receipt->receipt_number, 'date' => $receipt->received_at->toDateString(), 'currency' => $receipt->currency, 'amount' => T::money($receipt->amount_minor),
                'payer' => $receipt->payer_name, 'narration' => (string) $receipt->narration,
            ],
            'student' => ['name' => (string) $student?->fullName()],
            'tenders' => $receipt->tenders->map(fn ($t): array => ['type' => str_replace('_', ' ', $t->tender_type), 'reference' => (string) $t->reference, 'amount' => T::money($t->amount_minor)])->values()->all(),
        ], $generatedByUserId, $receipt->academic_year_id, $receipt->term_id, $receipt->getMorphClass(), $receipt->id);
    }
}
