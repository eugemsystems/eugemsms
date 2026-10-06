<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\ReturnSchemeOfWorkData;
use Modules\Academic\Models\SchemeOfWork;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

final class ReturnSchemeOfWorkAction extends Action
{
    public function execute(ReturnSchemeOfWorkData $data): SchemeOfWork
    {
        $scheme = SchemeOfWork::findOrFail($data->schemeOfWorkId);

        if ($scheme->status !== 'submitted') {
            throw new InvalidStateTransitionException(
                "Scheme of work #{$scheme->id} in [{$scheme->status}] cannot be returned.",
                ['scheme_of_work_id' => $scheme->id, 'status' => $scheme->status],
            );
        }

        if (trim($data->reviewComments) === '') {
            throw new InvalidArgumentException('Say what needs to change when returning a scheme of work.');
        }

        return $this->transaction(function () use ($scheme, $data): SchemeOfWork {
            $scheme->update(['status' => 'returned', 'reviewed_by' => $data->reviewedByUserId, 'review_comments' => $data->reviewComments]);

            return $scheme->fresh();
        });
    }
}
