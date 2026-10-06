<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Domain\DataObjects\AdvanceEnquiryStageData;
use Modules\People\Models\Enquiry;

/**
 * ACT-AdvanceEnquiryStage (Book C PPL-02 §2). Moves an enquiry along the funnel.
 * It may jump forward but never back, and may be marked lost from any open stage
 * — but only with one of the known reasons, so the funnel report can say why.
 * `converted` is set only by linking to a converted application.
 */
final class AdvanceEnquiryStageAction extends Action
{
    public const STAGES = ['new', 'contacted', 'information_sent', 'visit_booked', 'visited', 'applied'];

    public const LOST_REASONS = ['fees', 'distance', 'places_full', 'chose_other_school', 'no_response'];

    public function execute(AdvanceEnquiryStageData $data): Enquiry
    {
        $enquiry = Enquiry::findOrFail($data->enquiryId);

        if (in_array($enquiry->stage, ['lost', 'converted'], true)) {
            throw new InvalidStateTransitionException("Enquiry #{$enquiry->id} is already [{$enquiry->stage}].", ['enquiry_id' => $enquiry->id]);
        }

        if ($data->newStage === 'lost') {
            if (! in_array((string) $data->lostReason, self::LOST_REASONS, true)) {
                throw new InvalidArgumentException('Say why the enquiry was lost.');
            }

            return $this->transaction(function () use ($enquiry, $data): Enquiry {
                $enquiry->update(['stage' => 'lost', 'lost_reason' => $data->lostReason, 'next_follow_up_on' => null]);

                return $enquiry->fresh();
            });
        }

        $from = array_search($enquiry->stage, self::STAGES, true);
        $to = array_search($data->newStage, self::STAGES, true);

        if ($to === false || $from === false || $to <= $from) {
            throw new InvalidStateTransitionException("An enquiry cannot go from [{$enquiry->stage}] to [{$data->newStage}].", ['enquiry_id' => $enquiry->id]);
        }

        return $this->transaction(function () use ($enquiry, $data): Enquiry {
            $enquiry->update(['stage' => $data->newStage]);

            return $enquiry->fresh();
        });
    }
}
