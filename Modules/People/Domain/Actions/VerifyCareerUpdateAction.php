<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\VerifyCareerUpdateData;
use Modules\People\Models\AlumniCareerUpdate;

final class VerifyCareerUpdateAction extends Action
{
    public function execute(VerifyCareerUpdateData $data): AlumniCareerUpdate
    {
        $update = AlumniCareerUpdate::findOrFail($data->careerUpdateId);

        return $this->transaction(function () use ($update): AlumniCareerUpdate {
            $update->update(['verified' => true]);

            return $update->fresh();
        });
    }
}
