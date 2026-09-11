<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use App\Models\User;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\NotificationOptOut;
use Modules\People\Domain\DataObjects\OptOutAlumniContactData;
use Modules\People\Models\Alumnus;

/**
 * ACT-OptOutAlumniContact (Book K PPL-06 §4/BR-PPL-06-011/
 * AC-PPL-06-005). `alumni.status = 'opted_out'` is this module's own
 * source of truth for whether IT should reach out; the real
 * suppression of `CORE-09` channel sends is the `NotificationOptOut`
 * row(s) written here for every known address — the same mechanism
 * every other module's outreach already respects, not a second
 * opt-out system.
 */
final class OptOutAlumniContactAction extends Action
{
    public function execute(OptOutAlumniContactData $data): Alumnus
    {
        $alumnus = Alumnus::findOrFail($data->alumnusId);
        $user = $alumnus->user_id !== null ? User::find($alumnus->user_id) : null;

        return $this->transaction(function () use ($alumnus, $user, $data): Alumnus {
            foreach ($this->addressesFor($user) as $channel => $address) {
                NotificationOptOut::updateOrCreate(
                    ['school_id' => $alumnus->school_id, 'address' => $address, 'channel' => $channel],
                    ['reason' => $data->reason, 'opted_out_at' => Carbon::now()],
                );
            }

            $alumnus->update(['status' => 'opted_out']);

            return $alumnus->fresh();
        });
    }

    /**
     * @return array<string, string>
     */
    private function addressesFor(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $addresses = [];

        if ($user->email !== null) {
            $addresses['email'] = $user->email;
        }

        if ($user->phone !== null) {
            $addresses['sms'] = $user->phone;
            $addresses['whatsapp'] = $user->phone;
        }

        return $addresses;
    }
}
