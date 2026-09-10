<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Compliance\Domain\Events\StatutoryDocumentCriticallyExpired;
use Modules\Compliance\Models\Contract;
use Modules\Compliance\Models\StatutoryDocument;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CheckDocumentExpiry (Book H3 CMP-04 §3 ⭐/BR-CMP-04-005/006
 * (AC-CMP-04-002)). Recomputes `status` for every `statutory_documents`
 * AND `contracts` row with an `expires_on` — the same lead-time
 * mechanism covers both, per the module's own migration docblock on
 * why `contracts` reuses it rather than inventing a second one.
 * `StatutoryDocumentCriticallyExpired` fires for `isCritical()` documents
 * only, never for contracts (BR-CMP-04-006 names statutory documents
 * specifically).
 */
final class CheckDocumentExpiryAction extends Action
{
    /**
     * @return array{statutory_documents: array<int, StatutoryDocument>, contracts: array<int, Contract>}
     */
    public function execute(int $schoolId): array
    {
        $today = Carbon::now()->toDateString();

        $documents = StatutoryDocument::where('school_id', $schoolId)->whereNotNull('expires_on')->get();
        $updatedDocuments = [];

        foreach ($documents as $document) {
            $status = $this->statusFor($document->expires_on->toDateString(), $document->renewal_lead_days, $today);

            if ($status !== $document->status) {
                $document->update(['status' => $status]);

                if ($status === 'expired' && $document->isCritical()) {
                    event(new StatutoryDocumentCriticallyExpired($document));
                }
            }

            $updatedDocuments[] = $document;
        }

        $contracts = Contract::where('school_id', $schoolId)->whereNotNull('expires_on')->where('status', '!=', 'terminated')->get();
        $updatedContracts = [];

        foreach ($contracts as $contract) {
            $status = $this->statusFor($contract->expires_on->toDateString(), $contract->renewal_lead_days, $today);
            $contractStatus = match ($status) {
                'expired' => 'expired',
                'expiring' => 'expiring',
                default => 'active',
            };

            if ($contractStatus !== $contract->status) {
                $contract->update(['status' => $contractStatus]);
            }

            $updatedContracts[] = $contract;
        }

        return ['statutory_documents' => $updatedDocuments, 'contracts' => $updatedContracts];
    }

    private function statusFor(string $expiresOn, int $renewalLeadDays, string $today): string
    {
        if ($expiresOn < $today) {
            return 'expired';
        }

        $leadDate = Carbon::parse($expiresOn)->subDays($renewalLeadDays)->toDateString();

        return $leadDate <= $today ? 'expiring' : 'valid';
    }
}
