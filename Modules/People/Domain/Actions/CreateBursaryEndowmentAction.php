<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\AcademicYear;
use Modules\People\Domain\DataObjects\CreateBursaryEndowmentData;
use Modules\People\Domain\DataObjects\SyncEndowmentBudgetEnvelopeData;
use Modules\People\Models\BursaryEndowment;

/**
 * ACT-CreateBursaryEndowment (Book K PPL-06 §4/BR-PPL-06-008 ⭐/009).
 * If capital-funded (`endowment_capital_minor` given), the linked
 * `FIN-07` scheme's current-year budget envelope is synced
 * immediately so it's fundable from the moment the endowment exists.
 */
final class CreateBursaryEndowmentAction extends Action
{
    public function __construct(
        private readonly SyncEndowmentBudgetEnvelopeAction $syncEnvelope,
    ) {}

    public function execute(CreateBursaryEndowmentData $data): BursaryEndowment
    {
        return $this->transaction(function () use ($data): BursaryEndowment {
            $endowment = BursaryEndowment::create([
                'school_id' => $data->schoolId,
                'donor_name' => $data->donorName,
                'alumnus_id' => $data->alumnusId,
                'endowment_capital_minor' => $data->endowmentCapitalMinor,
                'annual_commitment_minor' => $data->annualCommitmentMinor,
                'currency' => $data->currency,
                'funds_scheme_id' => $data->fundsSchemeId,
                'named_recognition' => $data->namedRecognition,
                'is_anonymous' => $data->isAnonymous,
                'starts_on' => $data->startsOn->toDateString(),
                'status' => 'active',
            ]);

            if ($data->endowmentCapitalMinor !== null) {
                $currentYear = AcademicYear::query()
                    ->where('school_id', $data->schoolId)
                    ->where('is_current', true)
                    ->first();

                if ($currentYear !== null) {
                    $this->syncEnvelope->execute(new SyncEndowmentBudgetEnvelopeData(
                        bursaryEndowmentId: $endowment->id,
                        academicYearId: $currentYear->id,
                    ));
                }
            }

            return $endowment->fresh();
        });
    }
}
