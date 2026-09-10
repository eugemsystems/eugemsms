<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Stores\Models\QuotationRequest;

/**
 * ACT-AwardQuotation (Book H1 FIN-08 §6/BR-FIN-08-008/AC-FIN-08-011).
 * Awarding anything other than the lowest compliant bid requires a
 * written justification — enforced here, not merely a form hint.
 */
final class AwardQuotationAction extends Action
{
    public function execute(int $quotationRequestId, int $awardedQuotationId, int $awardedByUserId, ?string $justification = null): QuotationRequest
    {
        $request = QuotationRequest::with('quotations')->findOrFail($quotationRequestId);
        $awarded = $request->quotations->firstWhere('id', $awardedQuotationId);

        if ($awarded === null) {
            throw ValidationException::withMessages([
                'awardedQuotationId' => "Quotation #{$awardedQuotationId} does not belong to this request.",
            ]);
        }

        $lowestCompliant = $request->quotations
            ->where('is_compliant', true)
            ->sortBy('total_minor')
            ->first();

        $isLowest = $lowestCompliant !== null && $lowestCompliant->id === $awarded->id;

        if (! $isLowest && trim((string) $justification) === '') {
            throw ValidationException::withMessages([
                'justification' => 'Awarding to other than the lowest compliant quotation requires a written justification (BR-FIN-08-008).',
            ]);
        }

        return $this->transaction(function () use ($request, $awarded, $awardedByUserId, $justification): QuotationRequest {
            foreach ($request->quotations as $quotation) {
                $quotation->update(['status' => $quotation->id === $awarded->id ? 'awarded' : 'rejected']);
            }

            $request->update([
                'status' => 'awarded',
                'awarded_quotation_id' => $awarded->id,
                'award_justification' => $justification,
                'awarded_by' => $awardedByUserId,
            ]);

            return $request;
        });
    }
}
