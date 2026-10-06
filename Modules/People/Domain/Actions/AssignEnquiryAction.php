<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\AssignEnquiryData;
use Modules\People\Models\Enquiry;

/**
 * ACT-AssignEnquiry (Book C PPL-02 §2). Gives an enquiry an owner who is a
 * member of this school.
 */
final class AssignEnquiryAction extends Action
{
    public function execute(AssignEnquiryData $data): Enquiry
    {
        $enquiry = Enquiry::findOrFail($data->enquiryId);

        if ($data->assignedTo !== null && ! $enquiry->school->users()->whereKey($data->assignedTo)->exists()) {
            throw new InvalidArgumentException('That user is not a member of this school.');
        }

        return $this->transaction(function () use ($enquiry, $data): Enquiry {
            $enquiry->update(['assigned_to' => $data->assignedTo]);

            return $enquiry->fresh();
        });
    }
}
