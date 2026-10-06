<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\ApproveSchemeOfWorkData;
use Modules\Academic\Models\SchemeOfWork;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Models\Staff;

final class ApproveSchemeOfWorkAction extends Action
{
    public function execute(ApproveSchemeOfWorkData $data): SchemeOfWork
    {
        $scheme = SchemeOfWork::findOrFail($data->schemeOfWorkId);

        if ($scheme->status !== 'submitted') {
            throw new InvalidStateTransitionException(
                "Scheme of work #{$scheme->id} in [{$scheme->status}] cannot be approved.",
                ['scheme_of_work_id' => $scheme->id, 'status' => $scheme->status],
            );
        }

        if (Staff::query()->whereKey($scheme->teacher_staff_id)->where('user_id', $data->reviewedByUserId)->exists()) {
            throw new InvalidArgumentException('A teacher cannot approve their own scheme of work.');
        }

        return $this->transaction(function () use ($scheme, $data): SchemeOfWork {
            $scheme->update(['status' => 'approved', 'reviewed_by' => $data->reviewedByUserId, 'review_comments' => null]);

            return $scheme->fresh();
        });
    }
}
