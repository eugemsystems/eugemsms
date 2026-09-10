<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Illuminate\Support\Str;
use Modules\Boarding\Domain\DataObjects\ApproveExeatData;
use Modules\Boarding\Domain\Events\ExeatApproved;
use Modules\Boarding\Models\Exeat;
use Modules\Boarding\Models\ExeatQuota;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-ApproveExeat (Book F BRD-03 §4/BR-BRD-03-002/008/009). Generates
 * the pass's verification code — the QR image itself and
 * `pass_document_id` (`CORE-10` document generation) are deferred;
 * the code is the actually load-bearing artefact the gate terminal
 * checks against. Pre-populating the affected roll calls
 * (BR-BRD-03-009) is `OpenRollCallAction`'s own job — it now reads
 * approved/current exeats directly (see that action's own docblock).
 */
final class ApproveExeatAction extends Action
{
    public function execute(ApproveExeatData $data): Exeat
    {
        $exeat = Exeat::findOrFail($data->exeatId);

        if ($exeat->status !== 'pending') {
            throw new InvalidStateTransitionException(
                "Exeat #{$exeat->id} must be pending to be approved (currently {$exeat->status}).",
                ['exeat_id' => $exeat->id, 'status' => $exeat->status],
            );
        }

        return $this->transaction(function () use ($exeat): Exeat {
            $exeat->update([
                'status' => 'approved',
                'verification_code' => Str::random(32),
            ]);

            $quota = ExeatQuota::query()
                ->where('student_id', $exeat->student_id)
                ->where('term_id', $exeat->term_id)
                ->where('exeat_type_id', $exeat->exeat_type_id)
                ->first();

            if ($quota !== null) {
                $quota->decrement('pending');
                $quota->increment('used');
            }

            event(new ExeatApproved($exeat));

            return $exeat;
        });
    }
}
