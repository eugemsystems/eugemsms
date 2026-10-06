<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\LinkEnquiryToApplicationData;
use Modules\People\Models\Application;
use Modules\People\Models\Enquiry;

/**
 * ACT-LinkEnquiryToApplication (Book C PPL-02 §2). Ties an enquiry to the
 * application it became, moving it to `applied` — or `converted` if that
 * application already became a learner. One application belongs to one enquiry.
 */
final class LinkEnquiryToApplicationAction extends Action
{
    public function execute(LinkEnquiryToApplicationData $data): Enquiry
    {
        $enquiry = Enquiry::findOrFail($data->enquiryId);
        $application = Application::findOrFail($data->applicationId);

        if ($enquiry->stage === 'lost') {
            throw new InvalidArgumentException('A lost enquiry cannot be linked to an application.');
        }

        if (Enquiry::query()->where('application_id', $application->id)->where('id', '!=', $enquiry->id)->exists()) {
            throw new InvalidArgumentException('That application is already linked to another enquiry.');
        }

        return $this->transaction(function () use ($enquiry, $application): Enquiry {
            $enquiry->update(['application_id' => $application->id, 'stage' => $application->student_id !== null ? 'converted' : 'applied', 'next_follow_up_on' => null]);

            return $enquiry->fresh();
        });
    }
}
