<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use App\Models\User;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\NotificationOptOut;
use Modules\People\Domain\DataObjects\OptBackInAlumniContactData;
use Modules\People\Models\Alumnus;

/**
 * ACT-OptBackInAlumniContact (Book K PPL-06 §4/BR-PPL-06-011). Only
 * the alumnus themselves opts back in — this Action exists precisely
 * so nothing else in the system can silently do it for them.
 */
final class OptBackInAlumniContactAction extends Action
{
    public function execute(OptBackInAlumniContactData $data): Alumnus
    {
        $alumnus = Alumnus::findOrFail($data->alumnusId);
        $user = $alumnus->user_id !== null ? User::find($alumnus->user_id) : null;

        return $this->transaction(function () use ($alumnus, $user): Alumnus {
            if ($user !== null) {
                NotificationOptOut::where('school_id', $alumnus->school_id)
                    ->where(fn ($q) => $q->where('address', $user->email)->orWhere('address', $user->phone))
                    ->delete();
            }

            $alumnus->update(['status' => 'active']);

            return $alumnus->fresh();
        });
    }
}
