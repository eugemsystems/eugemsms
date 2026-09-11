<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\CreateDiscountSchemeData;
use Modules\Finance\Domain\Events\SchemeCreated;
use Modules\Finance\Models\DiscountScheme;

/**
 * ACT-CreateDiscountScheme (Book K FIN-07 §2/§4).
 */
final class CreateDiscountSchemeAction extends Action
{
    public function execute(CreateDiscountSchemeData $data): DiscountScheme
    {
        return $this->transaction(function () use ($data): DiscountScheme {
            $scheme = DiscountScheme::create([
                'school_id' => $data->schoolId,
                'code' => $data->code,
                'name' => $data->name,
                'scheme_type' => $data->schemeType,
                'category' => $data->category,
                'calculation_method' => $data->calculationMethod,
                'applies_to_components' => $data->appliesToComponents,
                'default_percent' => $data->defaultPercent,
                'default_amount_minor' => $data->defaultAmountMinor,
                'currency' => $data->currency,
                'tier_bands' => $data->tierBands,
                'requires_means_assessment' => $data->requiresMeansAssessment,
                'requires_academic_threshold' => $data->requiresAcademicThreshold,
                'minimum_average_percent' => $data->minimumAveragePercent,
                'requires_approval' => $data->requiresApproval,
                'approval_chain_id' => $data->approvalChainId,
                'is_sponsor_funded' => $data->isSponsorFunded,
                'contra_account_id' => $data->contraAccountId,
                'renewal_frequency' => $data->renewalFrequency,
                'is_active' => true,
            ]);

            event(new SchemeCreated($scheme));

            return $scheme;
        });
    }
}
