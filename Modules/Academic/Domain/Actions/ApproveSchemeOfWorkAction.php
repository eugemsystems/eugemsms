<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\ApproveSchemeOfWorkData;
use Modules\Academic\Models\SchemeOfWork;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

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

        return $this->transaction(function () use ($scheme, $data): SchemeOfWork {
            $scheme->update(['status' => 'approved', 'reviewed_by' => $data->reviewedByUserId, 'review_comments' => null]);

            return $scheme->fresh();
        });
    }
}
