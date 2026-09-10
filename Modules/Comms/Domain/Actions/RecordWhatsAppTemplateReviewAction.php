<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Comms\Domain\Events\TemplateApproved;
use Modules\Comms\Domain\Events\TemplateRejected;
use Modules\Comms\Models\WhatsAppTemplate;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-RecordWhatsAppTemplateReview (Book I COM-01 §3/BR-COM-01-005).
 * Records Meta's own review decision — never invented locally.
 */
final class RecordWhatsAppTemplateReviewAction extends Action
{
    private const array VALID_DECISIONS = ['approved', 'rejected'];

    public function execute(int $templateId, string $decision, ?string $metaTemplateId = null, ?string $rejectionReason = null): WhatsAppTemplate
    {
        if (! in_array($decision, self::VALID_DECISIONS, true)) {
            throw new InvalidStateTransitionException(
                "WhatsApp template review decision must be 'approved' or 'rejected', got '{$decision}'.",
                ['decision' => $decision],
            );
        }

        return $this->transaction(function () use ($templateId, $decision, $metaTemplateId, $rejectionReason): WhatsAppTemplate {
            $template = WhatsAppTemplate::findOrFail($templateId);

            $template->update([
                'review_status' => $decision,
                'meta_template_id' => $decision === 'approved' ? $metaTemplateId : null,
                'approved_at' => $decision === 'approved' ? Carbon::now() : null,
                'rejection_reason' => $decision === 'rejected' ? $rejectionReason : null,
            ]);

            if ($decision === 'approved') {
                event(new TemplateApproved($template));
            } else {
                event(new TemplateRejected($template));
            }

            return $template;
        });
    }
}
