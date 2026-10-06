<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\VerifyGuardianData;
use Modules\People\Models\Guardian;
use Modules\People\Models\GuardianVerification;

/**
 * ACT-VerifyGuardian (Book C PPL-03 §3). Confirms a recorded ID check. The
 * document and the collection photo must both be on file, and a guardian who is
 * also a user cannot verify themselves.
 */
final class VerifyGuardianAction extends Action
{
    public function execute(VerifyGuardianData $data): GuardianVerification
    {
        $verification = GuardianVerification::findOrFail($data->verificationId);

        if ($verification->document_file_id === null || $verification->photo_file_id === null) {
            throw new InvalidArgumentException('Attach both the ID document and the collection photo before verifying.');
        }

        if (Guardian::query()->whereKey($verification->guardian_id)->where('user_id', $data->verifiedByUserId)->exists()) {
            throw new InvalidArgumentException('A guardian cannot verify themselves.');
        }

        return $this->transaction(function () use ($verification, $data): GuardianVerification {
            $verification->update(['verified_by' => $data->verifiedByUserId, 'verified_at' => Carbon::now()]);

            return $verification->fresh();
        });
    }
}
