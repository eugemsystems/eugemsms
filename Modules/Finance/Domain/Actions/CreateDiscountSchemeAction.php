<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\CreateDiscountSchemeData;
use Modules\Finance\Domain\Events\SchemeCreated;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\DiscountScheme;

/**
 * ACT-CreateDiscountScheme (Book K FIN-07 §2/§4).
 */
final class CreateDiscountSchemeAction extends Action
{
    public function execute(CreateDiscountSchemeData $data): DiscountScheme
    {
        $this->assertValid($data);

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

    private function assertValid(CreateDiscountSchemeData $data): void
    {
        if (preg_match('/^[A-Z0-9_]{2,30}$/', $data->code) !== 1 || trim($data->name) === '' || mb_strlen($data->name) > 150) {
            throw new InvalidArgumentException('A scheme needs an upper-case code (letters, digits, underscore) and a name.');
        }

        if (! in_array($data->schemeType, ['automatic', 'application_based', 'individually_granted'], true)
            || ! in_array($data->category, ['sibling', 'staff', 'academic', 'sport', 'hardship', 'orphan', 'corporate', 'church', 'early_settlement'], true)
            || ! in_array($data->calculationMethod, ['percentage', 'fixed_amount', 'tiered'], true)) {
            throw new InvalidArgumentException('That scheme type, category or calculation method is not recognised.');
        }

        if ($data->schemeType === 'automatic' && ! in_array($data->category, ['sibling', 'staff'], true)) {
            throw new InvalidArgumentException('Only sibling and staff-child schemes can be automatic — no other eligibility rule exists to evaluate.');
        }

        // A scheme's default is optional — an award carries its own figure — but one that
        // is given must make sense, and an automatic staff scheme has nothing else to read.
        if ($data->defaultPercent !== null && (! is_numeric($data->defaultPercent) || (float) $data->defaultPercent <= 0 || (float) $data->defaultPercent > 100)) {
            throw new InvalidArgumentException('A default percentage must be between 0 and 100.');
        }

        if ($data->defaultAmountMinor !== null && ($data->defaultAmountMinor <= 0 || $data->currency === null)) {
            throw new InvalidArgumentException('A default fixed amount needs to be positive and have a currency.');
        }

        if ($data->schemeType === 'automatic' && $data->category === 'staff' && $data->defaultPercent === null) {
            throw new InvalidArgumentException('An automatic staff-child scheme needs its default percentage.');
        }

        if ($data->requiresAcademicThreshold && ($data->minimumAveragePercent === null || ! is_numeric($data->minimumAveragePercent) || (float) $data->minimumAveragePercent <= 0 || (float) $data->minimumAveragePercent > 100)) {
            throw new InvalidArgumentException('An academic threshold needs a minimum average between 0 and 100.');
        }

        foreach ($data->tierBands ?? [] as $band) {
            if ($band['nth'] < 2 || ! is_numeric($band['percent']) || (float) $band['percent'] <= 0 || (float) $band['percent'] > 100) {
                throw new InvalidArgumentException('Each tier band needs a child position of 2 or more and a percentage between 0 and 100.');
            }
        }

        if ($data->schemeType === 'automatic' && $data->category === 'sibling' && ($data->tierBands ?? []) === []) {
            throw new InvalidArgumentException('An automatic sibling scheme needs its tier bands.');
        }

        // The contra account must be this school's own, postable account — never another school's.
        Account::query()->where('school_id', $data->schoolId)->where('is_postable', true)->findOrFail($data->contraAccountId);

        if (DiscountScheme::query()->where('school_id', $data->schoolId)->where('code', $data->code)->exists()) {
            throw new InvalidArgumentException("A scheme with code {$data->code} already exists.");
        }
    }
}
