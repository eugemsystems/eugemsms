<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\CreateSponsorshipData;
use Modules\People\Models\Guardian;
use Modules\People\Models\Sponsorship;

/**
 * ACT-CreateSponsorship (Book C PPL-03 §3/BR-PPL-03-018/019). An organisation's
 * funding programme. The sponsor must be an organisation guardian; a budget
 * needs a currency; it starts as a draft until activated.
 */
final class CreateSponsorshipAction extends Action
{
    public const TYPES = ['full', 'partial', 'component_specific', 'capped'];

    public function execute(CreateSponsorshipData $data): Sponsorship
    {
        $sponsor = Guardian::query()->find($data->guardianId);

        if ($sponsor === null || $sponsor->guardian_type !== 'organisation') {
            throw new InvalidArgumentException('A sponsor must be an organisation guardian of this school.');
        }

        if (trim($data->name) === '' || ! in_array($data->sponsorshipType, self::TYPES, true)) {
            throw new InvalidArgumentException('A sponsorship needs a name and a known type.');
        }

        if ($data->budgetMinor !== null && ($data->budgetMinor <= 0 || $data->budgetCurrency === null || strlen($data->budgetCurrency) !== 3)) {
            throw new InvalidArgumentException('A budget must be positive and carry a three-letter currency.');
        }

        if ($data->endsOn !== null && $data->endsOn->lt($data->startsOn)) {
            throw new InvalidArgumentException('A sponsorship cannot end before it starts.');
        }

        if ($data->maxBeneficiaries !== null && $data->maxBeneficiaries < 1) {
            throw new InvalidArgumentException('The beneficiary limit must be at least 1.');
        }

        return $this->transaction(fn (): Sponsorship => Sponsorship::create([
            'school_id' => $data->schoolId,
            'guardian_id' => $data->guardianId,
            'name' => trim($data->name),
            'sponsorship_type' => $data->sponsorshipType,
            'budget_minor' => $data->budgetMinor,
            'budget_currency' => $data->budgetMinor === null ? null : strtoupper((string) $data->budgetCurrency),
            'max_beneficiaries' => $data->maxBeneficiaries,
            'starts_on' => $data->startsOn->toDateString(),
            'ends_on' => $data->endsOn?->toDateString(),
            'status' => 'draft',
            'contact_person' => $data->contactPerson,
            'reporting_frequency' => $data->reportingFrequency,
            'created_by' => $data->createdByUserId,
        ]));
    }
}
