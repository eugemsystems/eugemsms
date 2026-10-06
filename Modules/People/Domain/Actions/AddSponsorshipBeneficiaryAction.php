<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Domain\DataObjects\AddSponsorshipBeneficiaryData;
use Modules\People\Domain\DataObjects\CreateFeeLiabilityData;
use Modules\People\Models\Sponsorship;
use Modules\People\Models\SponsorshipBeneficiary;
use Modules\People\Models\Student;

/**
 * ACT-AddSponsorshipBeneficiary (Book C PPL-03 §3/BR-PPL-03-018/019). Puts a
 * learner under a sponsorship and creates the real fee liability that bills the
 * sponsor — the school's income is unchanged, only the payer. The beneficiary
 * limit and the budget envelope are enforced here: a commitment that would pass
 * the cap is refused naming the shortfall.
 */
final class AddSponsorshipBeneficiaryAction extends Action
{
    public function __construct(private readonly CreateFeeLiabilityAction $createFeeLiability) {}

    public function execute(AddSponsorshipBeneficiaryData $data): SponsorshipBeneficiary
    {
        $sponsorship = Sponsorship::query()->lockForUpdate()->findOrFail($data->sponsorshipId);

        if (! in_array($sponsorship->status, ['draft', 'active'], true)) {
            throw new InvalidStateTransitionException("Sponsorship #{$sponsorship->id} is [{$sponsorship->status}] and takes no new beneficiaries.", ['sponsorship_id' => $sponsorship->id]);
        }

        $student = Student::findOrFail($data->studentId);

        if (SponsorshipBeneficiary::query()->where('sponsorship_id', $sponsorship->id)->where('student_id', $student->id)->exists()) {
            throw new InvalidArgumentException('That learner already benefits from this sponsorship.');
        }

        $active = SponsorshipBeneficiary::query()->where('sponsorship_id', $sponsorship->id)->where('status', 'active')->count();

        if ($sponsorship->max_beneficiaries !== null && $active >= $sponsorship->max_beneficiaries) {
            throw new InvalidArgumentException("This sponsorship is limited to {$sponsorship->max_beneficiaries} beneficiaries.");
        }

        if ($data->commitmentMinor < 0) {
            throw new InvalidArgumentException('A commitment cannot be negative.');
        }

        if ($sponsorship->budget_minor !== null && $sponsorship->committed_minor + $data->commitmentMinor > $sponsorship->budget_minor) {
            $over = $sponsorship->committed_minor + $data->commitmentMinor - $sponsorship->budget_minor;

            throw new InvalidArgumentException('This would exceed the sponsorship budget by '.number_format($over / 100, 2).' '.$sponsorship->budget_currency.'.');
        }

        $startsOn = ($data->startsOn ?? Carbon::today());

        return $this->transaction(function () use ($sponsorship, $student, $data, $startsOn): SponsorshipBeneficiary {
            $liability = $this->createFeeLiability->execute(new CreateFeeLiabilityData(
                schoolId: $sponsorship->school_id,
                studentId: $student->id,
                guardianId: $sponsorship->guardian_id,
                shareType: 'full_component',
                createdByUserId: $data->createdByUserId,
                priority: 10,
                effectiveFrom: $startsOn,
            ));

            $sponsorship->increment('committed_minor', $data->commitmentMinor);

            return SponsorshipBeneficiary::create([
                'school_id' => $sponsorship->school_id,
                'sponsorship_id' => $sponsorship->id,
                'student_id' => $student->id,
                'fee_liability_id' => $liability->id,
                'starts_on' => $startsOn->toDateString(),
                'status' => 'active',
                'performance_condition' => $data->performanceCondition,
            ]);
        });
    }
}
