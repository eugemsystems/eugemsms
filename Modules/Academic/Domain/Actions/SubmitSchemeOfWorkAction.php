<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\SubmitSchemeOfWorkData;
use Modules\Academic\Models\SchemeOfWork;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

final class SubmitSchemeOfWorkAction extends Action
{
    public function execute(SubmitSchemeOfWorkData $data): SchemeOfWork
    {
        $scheme = SchemeOfWork::findOrFail($data->schemeOfWorkId);

        if (! in_array($scheme->status, ['draft', 'returned'], true)) {
            throw new InvalidStateTransitionException(
                "Scheme of work #{$scheme->id} in [{$scheme->status}] cannot be submitted.",
                ['scheme_of_work_id' => $scheme->id, 'status' => $scheme->status],
            );
        }

        return $this->transaction(function () use ($scheme): SchemeOfWork {
            $scheme->update(['status' => 'submitted']);

            return $scheme->fresh();
        });
    }
}
