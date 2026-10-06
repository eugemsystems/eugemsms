<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Auth\CreateUserAction;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\Actions\Schools\AssignUserToSchoolAction;
use Modules\Core\Domain\DataObjects\Auth\CreateUserData;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\Core\Domain\DataObjects\Schools\AssignUserData;
use Modules\Core\Domain\Support\Auth\PhoneNormalizer;
use Modules\Core\Domain\Support\Auth\UserType;
use Modules\Core\Models\School;
use Modules\People\Models\Guardian;
use Modules\People\Models\StudentGuardian;
use Throwable;

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
        private readonly DispatchNotificationAction $dispatchNotification,
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

        $granted = $this->transaction(function () use ($guardian, $phone, $school, $grantedByUserId): Guardian {
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

        $this->invite($granted, $school, $phone);

        return $granted;
    }

    /**
     * Tells the guardian, by SMS and email where they have one, that they can now use the app. A
     * failed send never undoes the access that was just granted.
     */
    private function invite(Guardian $guardian, School $school, string $phone): void
    {
        $addresses = ['sms' => $phone];

        if ($guardian->email !== null && $guardian->email !== '') {
            $addresses['email'] = $guardian->email;
        }

        try {
            $this->dispatchNotification->execute(new DispatchNotificationData(
                schoolId: $school->id,
                notificationKey: 'people.parent_app_invitation',
                recipientType: 'guardian',
                addresses: $addresses,
                context: ['guardian' => ['name' => trim($guardian->first_name.' '.$guardian->last_name), 'phone' => $phone], 'school' => ['name' => $school->name]],
                recipientId: $guardian->id,
                relatedType: 'guardian_portal_access',
                relatedId: $guardian->id,
                dedupeWindowMinutes: 1440,
            ));
        } catch (Throwable) {
            // The invitation is a courtesy; access stands without it.
        }
    }
}
