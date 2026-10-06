<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\RequestGuardianContactUpdateData;
use Modules\People\Domain\Support\PhoneNumberNormaliser;
use Modules\People\Models\Guardian;
use Modules\People\Models\GuardianContactUpdate;

/**
 * ACT-RequestGuardianContactUpdate (Book C PPL-03 §6/BR-PPL-03-021). Phone,
 * email and address control notification delivery and account recovery, so a
 * change a guardian asks for waits in a queue and takes no effect until staff
 * approve it. Phones are normalised to E.164 now so a bad number is refused to
 * the guardian, not found by the approver.
 */
final class RequestGuardianContactUpdateAction extends Action
{
    public const FIELDS = ['primary_phone', 'alternate_phone', 'whatsapp_phone', 'email', 'address_line_1', 'address_line_2', 'city', 'province'];

    public function execute(RequestGuardianContactUpdateData $data): GuardianContactUpdate
    {
        $guardian = Guardian::findOrFail($data->guardianId);
        $changes = [];

        foreach ($data->changes as $field => $value) {
            if (! in_array($field, self::FIELDS, true)) {
                throw new InvalidArgumentException("[{$field}] cannot be changed through a contact update.");
            }

            $value = $value === null ? null : trim((string) $value);

            if ($value !== null && $value !== '' && str_ends_with($field, '_phone')) {
                $value = PhoneNumberNormaliser::normalise($value);
            }

            if ($field === 'email' && $value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                throw new InvalidArgumentException('That email address is not valid.');
            }

            if ($value !== ($guardian->{$field} ?? null)) {
                $changes[$field] = $value === '' ? null : $value;
            }
        }

        if ($changes === []) {
            throw new InvalidArgumentException('Nothing in that request differs from the current details.');
        }

        if (GuardianContactUpdate::query()->where('guardian_id', $guardian->id)->where('status', 'pending')->exists()) {
            throw new InvalidArgumentException('A contact update for this guardian is already waiting for approval.');
        }

        return $this->transaction(fn (): GuardianContactUpdate => GuardianContactUpdate::create([
            'school_id' => $guardian->school_id,
            'guardian_id' => $guardian->id,
            'changes' => $changes,
            'status' => 'pending',
            'requested_by' => $data->requestedByUserId,
            'created_at' => now(),
        ]));
    }
}
