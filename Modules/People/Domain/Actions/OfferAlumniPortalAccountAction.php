<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Auth\CreateUserAction;
use Modules\Core\Domain\DataObjects\Auth\CreateUserData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\Auth\UserType;
use Modules\People\Domain\DataObjects\OfferAlumniPortalAccountData;
use Modules\People\Models\Alumnus;
use Modules\People\Models\Student;

/**
 * ACT-OfferAlumniPortalAccount (Book K PPL-06 §3/BR-PPL-06-012). A
 * NEW identity distinct from the graduate's now-closed learner
 * account — `UserType::Alumni` carries none of a learner account's
 * own roles/permissions, and this Action never touches
 * `student.user_id`. Full request-time enforcement that an alumnus
 * token cannot reach current-learner endpoints (AC-PPL-06-006) is an
 * HTTP/API-layer concern not built in this backend-only pass, same
 * scope boundary every other Book K module documents for itself.
 */
final class OfferAlumniPortalAccountAction extends Action
{
    public function __construct(
        private readonly CreateUserAction $createUser,
    ) {}

    public function execute(OfferAlumniPortalAccountData $data): Alumnus
    {
        $alumnus = Alumnus::findOrFail($data->alumnusId);

        if ($alumnus->user_id !== null) {
            throw new InvalidStateTransitionException(
                "Alumnus #{$alumnus->id} already has a portal account.",
                ['alumnus_id' => $alumnus->id],
            );
        }

        $student = Student::findOrFail($alumnus->student_id);

        return $this->transaction(function () use ($alumnus, $student, $data): Alumnus {
            $user = $this->createUser->execute(new CreateUserData(
                firstName: $student->first_name,
                lastName: $student->last_name,
                email: $data->email,
                phone: $data->phone,
                userType: UserType::Alumni,
                tenantId: $alumnus->school_id,
            ));

            $alumnus->update(['user_id' => $user->id]);

            return $alumnus->fresh();
        });
    }
}
