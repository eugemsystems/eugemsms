<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Models\AcademicYear;
use Modules\Finance\Models\DiscountScheme;
use Modules\People\Domain\DataObjects\CreateBursaryEndowmentData;
use Modules\People\Domain\DataObjects\SyncEndowmentBudgetEnvelopeData;
use Modules\People\Models\Alumnus;
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
        if (trim($data->donorName) === '' || mb_strlen($data->donorName) > 200 || Currency::tryFrom($data->currency) === null) {
            throw new InvalidArgumentException('An endowment needs the donor’s name and a supported currency.');
        }

        foreach ([$data->endowmentCapitalMinor, $data->annualCommitmentMinor] as $figure) {
            if ($figure !== null && $figure < 0) {
                throw new InvalidArgumentException('Endowment figures cannot be negative.');
            }
        }

        if (($data->endowmentCapitalMinor ?? 0) === 0 && ($data->annualCommitmentMinor ?? 0) === 0) {
            throw new InvalidArgumentException('An endowment needs capital, an annual commitment, or both.');
        }

        // The scheme it funds must be this school's own.
        DiscountScheme::query()->where('school_id', $data->schoolId)->findOrFail($data->fundsSchemeId);

        if ($data->alumnusId !== null) {
            Alumnus::query()->where('school_id', $data->schoolId)->findOrFail($data->alumnusId);
        }

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
