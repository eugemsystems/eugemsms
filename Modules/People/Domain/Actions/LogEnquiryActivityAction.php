<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\LogEnquiryActivityData;
use Modules\People\Models\Enquiry;
use Modules\People\Models\EnquiryActivity;

/**
 * ACT-LogEnquiryActivity (Book C PPL-02 §2). A call, visit or note against an
 * enquiry. It can set the next follow-up date; a first contact moves a `new`
 * enquiry to `contacted`.
 */
final class LogEnquiryActivityAction extends Action
{
    public const TYPES = ['call', 'email', 'whatsapp', 'sms', 'visit', 'note'];

    public function execute(LogEnquiryActivityData $data): EnquiryActivity
    {
        $enquiry = Enquiry::findOrFail($data->enquiryId);

        if (! in_array($data->activityType, self::TYPES, true) || trim($data->summary) === '') {
            throw new InvalidArgumentException('An activity needs a known type and a summary.');
        }

        if (in_array($enquiry->stage, ['lost', 'converted'], true)) {
            throw new InvalidArgumentException('That enquiry is closed.');
        }

        return $this->transaction(function () use ($enquiry, $data): EnquiryActivity {
            $activity = EnquiryActivity::create([
                'school_id' => $enquiry->school_id,
                'enquiry_id' => $enquiry->id,
                'activity_type' => $data->activityType,
                'summary' => mb_substr(trim($data->summary), 0, 500),
                'outcome' => $data->outcome,
                'performed_by' => $data->performedByUserId,
                'occurred_at' => ($data->occurredAt ?? Carbon::now())->toDateTimeString(),
            ]);

            $enquiry->update([
                'stage' => $enquiry->stage === 'new' && $data->activityType !== 'note' ? 'contacted' : $enquiry->stage,
                'next_follow_up_on' => $data->nextFollowUpOn?->toDateString() ?? $enquiry->next_follow_up_on?->toDateString(),
            ]);

            return $activity;
        });
    }
}
