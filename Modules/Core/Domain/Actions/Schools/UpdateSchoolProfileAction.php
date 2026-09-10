<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Schools;

use Illuminate\Support\Facades\Validator;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Contracts\Schools\SchoolLifecycleGuard;
use Modules\Core\Domain\DataObjects\Schools\UpdateSchoolData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Models\School;

/**
 * ACT-UpdateSchoolProfile (Book A CORE-02 §3). `code` is never accepted
 * here — BR-CORE-02-001 makes it immutable after creation.
 * BR-CORE-02-010: a `baseCurrency` change is rejected once the school has
 * any recorded financial transaction.
 */
final class UpdateSchoolProfileAction extends Action
{
    public function __construct(
        private readonly SchoolLifecycleGuard $lifecycleGuard,
    ) {}

    public function execute(UpdateSchoolData $data): School
    {
        $school = School::query()->findOrFail($data->schoolId);

        // `sometimes` only skips a field that is genuinely *absent* from
        // the input array — a present-but-null value still gets
        // validated against `string`/`in:...`/etc. and fails. Since
        // every DTO property defaults to null when "leave unchanged",
        // the array passed to the validator must drop null entries so
        // `sometimes` sees them as absent, not present-and-invalid.
        Validator::make(
            array_filter([
                'name' => $data->name,
                'category' => $data->category,
                'centre_number' => $data->centreNumber,
                'base_currency' => $data->baseCurrency,
                'latitude' => $data->latitude,
                'longitude' => $data->longitude,
                'email' => $data->email,
                'website' => $data->website,
                'primary_colour' => $data->primaryColour,
                'secondary_colour' => $data->secondaryColour,
            ], fn (mixed $value): bool => $value !== null),
            [
                'name' => ['sometimes', 'string', 'max:200'],
                'category' => ['sometimes', 'in:government,council,mission,trust,private'],
                'centre_number' => ['sometimes', 'nullable', 'string', 'max:20', 'unique:schools,centre_number,'.$school->id],
                'base_currency' => ['sometimes', 'string', 'size:3'],
                'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
                'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
                'email' => ['sometimes', 'nullable', 'email', 'max:150'],
                'website' => ['sometimes', 'nullable', 'url', 'max:200'],
                'primary_colour' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
                'secondary_colour' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            ],
        )->validate();

        if ($data->baseCurrency !== null
            && $data->baseCurrency !== $school->base_currency
            && $this->lifecycleGuard->hasFinancialActivity($school)) {
            throw new InvalidStateTransitionException(
                'The base currency cannot be changed once the school has recorded financial transactions.',
                ['school_id' => $school->id],
            );
        }

        return $this->transaction(function () use ($school, $data): School {
            $school->fill(array_filter([
                'name' => $data->name,
                'short_name' => $data->shortName,
                'centre_number' => $data->centreNumber,
                'emis_code' => $data->emisCode,
                'category' => $data->category,
                'responsible_authority' => $data->responsibleAuthority,
                'band' => $data->band,
                'province' => $data->province,
                'district' => $data->district,
                'address_line_1' => $data->addressLine1,
                'address_line_2' => $data->addressLine2,
                'city' => $data->city,
                'latitude' => $data->latitude,
                'longitude' => $data->longitude,
                'phone' => $data->phone,
                'email' => $data->email,
                'website' => $data->website,
                'motto' => $data->motto,
                'logo_path' => $data->logoPath,
                'crest_path' => $data->crestPath,
                'letterhead_path' => $data->letterheadPath,
                'primary_colour' => $data->primaryColour,
                'secondary_colour' => $data->secondaryColour,
                'base_currency' => $data->baseCurrency,
                'timezone' => $data->timezone,
                'locale' => $data->locale,
                'head_user_id' => $data->headUserId,
            ], fn (mixed $value): bool => $value !== null));

            $school->updated_by = $data->actingUserId;
            $school->save();

            return $school;
        });
    }
}
