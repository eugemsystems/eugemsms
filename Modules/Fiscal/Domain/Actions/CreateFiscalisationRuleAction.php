<?php

declare(strict_types=1);

namespace Modules\Fiscal\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Fiscal\Domain\DataObjects\CreateFiscalisationRuleData;
use Modules\Fiscal\Models\FiscalisationRule;

/**
 * ACT-CreateFiscalisationRule (Book H3 FIN-13 §3/BR-FIN-13-002). A
 * rule requires a `rationale` and an accountant's `reviewed_by`
 * sign-off before it can activate — this action doesn't enforce
 * review as a SEPARATE approval step (no CORE-07 chain wired here,
 * the same documented boundary held throughout this codebase); the
 * reviewer is recorded at creation, matching how this pass's other
 * "one accountable signer" gates work.
 */
final class CreateFiscalisationRuleAction extends Action
{
    public function execute(CreateFiscalisationRuleData $data): FiscalisationRule
    {
        return $this->transaction(fn (): FiscalisationRule => FiscalisationRule::create([
            'school_id' => $data->schoolId,
            'rule_name' => $data->ruleName,
            'source_type' => $data->sourceType,
            'source_identifier' => $data->sourceIdentifier,
            'is_fiscalisable' => $data->isFiscalisable,
            'tax_type' => $data->taxType,
            'tax_rate_percent' => $data->taxRatePercent,
            'tax_code' => $data->taxCode,
            'rationale' => $data->rationale,
            'priority' => $data->priority,
            'is_active' => true,
            'reviewed_by' => $data->reviewedByUserId,
            'reviewed_at' => Carbon::now(),
        ]));
    }
}
