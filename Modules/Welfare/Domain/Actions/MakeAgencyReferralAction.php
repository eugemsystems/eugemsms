<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Welfare\Domain\DataObjects\MakeAgencyReferralData;
use Modules\Welfare\Domain\Events\AgencyReferralMade;
use Modules\Welfare\Models\AgencyReferral;
use Modules\Welfare\Models\SafeguardingCase;

/**
 * ACT-MakeAgencyReferral (Book G BRD-08 §2/BR-BRD-08-014 — records the
 * legal basis for sharing information).
 */
final class MakeAgencyReferralAction extends Action
{
    public function execute(MakeAgencyReferralData $data): AgencyReferral
    {
        return $this->transaction(function () use ($data): AgencyReferral {
            $referral = AgencyReferral::create([
                'school_id' => $data->schoolId,
                'case_id' => $data->caseId,
                'agency_type' => $data->agencyType,
                'agency_name' => $data->agencyName,
                'contact_person' => $data->contactPerson,
                'referred_at' => $data->referredAt,
                'referred_by' => $data->referredByUserId,
                'reason' => $data->reason,
                'information_shared' => $data->informationShared,
                'consent_basis' => $data->consentBasis,
                'status' => 'made',
            ]);

            SafeguardingCase::whereKey($data->caseId)->update(['external_agency_involved' => true]);

            event(new AgencyReferralMade($referral));

            return $referral;
        });
    }
}
