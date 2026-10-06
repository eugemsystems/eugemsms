<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Auth\CreateUserAction;
use Modules\Core\Domain\Actions\Schools\AssignUserToSchoolAction;
use Modules\Core\Domain\DataObjects\Auth\CreateUserData;
use Modules\Core\Domain\DataObjects\Schools\AssignUserData;
use Modules\Core\Domain\Support\Auth\PhoneNormalizer;
use Modules\Core\Domain\Support\Auth\UserType;
use Modules\Core\Models\School;
use Modules\People\Models\Guardian;
use Modules\People\Models\StudentGuardian;

/**
 * ACT-GrantGuardianPortalAccess (Book C PPL-03 §6). Lets a guardian sign in to the parent app: a
 * parent account is created for their phone number (or the existing account for that number in the
 * tenant is linked), tied to the guardian record and attached to the school. The parent then
 * signs in with a one-time code sent to that phone — no password is ever set here. Refused unless
 * the guardian has a phone on file, is currently linked to at least one learner who is still at the
 * school, and has not already been given access; and an account that belongs to a different
 * guardian, or to vendor staff, is never reused.
 */
final class GrantGuardianPortalAccessAction extends Action
{
    public function __construct(
        private readonly CreateUserAction $createUser,
        private readonly AssignUserToSchoolAction $assignToSchool,
    ) {}

    public function execute(int $guardianId, int $grantedByUserId): Guardian
    {
        $guardian = Guardian::query()->findOrFail($guardianId);
        $errors = [];

        if ($guardian->user_id !== null) {
            $errors['guardian'] = 'This guardian already has portal access.';
        }

        $phone = $guardian->primary_phone !== null && trim($guardian->primary_phone) !== '' ? PhoneNormalizer::toE164($guardian->primary_phone) : null;

        if ($phone === null) {
            $errors['phone'] = 'The guardian needs a phone number on file — the parent app signs in with a code sent to it.';
        }

        $hasLearner = StudentGuardian::query()->where('guardian_id', $guardian->id)->where('status', 'active')
            ->whereHas('student', fn ($q) => $q->whereIn('status', ['enrolled', 'active', 'suspended']))->exists();

        if (! $hasLearner) {
            $errors['learner'] = 'The guardian is not linked to any learner currently at the school.';
        }

        if ($errors !== [] || $phone === null) {
            throw ValidationException::withMessages($errors);
        }

        $school = School::query()->findOrFail($guardian->school_id);

        return $this->transaction(function () use ($guardian, $phone, $school, $grantedByUserId): Guardian {
            $user = User::query()->where('tenant_id', $school->tenant_id)->where('phone', $phone)->first();

            if ($user !== null && ($user->user_type === UserType::Vendor || Guardian::query()->withoutGlobalScopes()->where('user_id', $user->id)->exists())) {
                throw ValidationException::withMessages(['phone' => 'That phone number already belongs to another account.']);
            }

            $user ??= $this->createUser->execute(new CreateUserData(
                firstName: $guardian->first_name,
                lastName: $guardian->last_name,
                email: $guardian->email,
                phone: $phone,
                userType: UserType::Parent,
                tenantId: $school->tenant_id,
                createdByUserId: $grantedByUserId,
            ));

            $this->assignToSchool->execute(new AssignUserData($school->id, $user->id, $grantedByUserId, isPrimary: $user->schools()->count() === 0));
            $guardian->update(['user_id' => $user->id]);

            return $guardian;
        });
    }
}
