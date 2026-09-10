<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Comms\Domain\DataObjects\SubmitWhatsAppTemplateData;
use Modules\Comms\Domain\Events\TemplateSubmitted;
use Modules\Comms\Models\WhatsAppTemplate;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-SubmitWhatsAppTemplate (Book I COM-01 §3 ⭐/BR-COM-01-006). Meta
 * pre-approval is external — this records the submission and starts
 * `review_status` at `pending`; `ApproveWhatsAppTemplateAction`/
 * `RejectWhatsAppTemplateAction` record Meta's own decision when it
 * comes back. `category` is fixed at submission per BR-COM-01-006 —
 * this action has no "change category" path at all, deliberately.
 */
final class SubmitWhatsAppTemplateAction extends Action
{
    public function execute(SubmitWhatsAppTemplateData $data): WhatsAppTemplate
    {
        return $this->transaction(function () use ($data): WhatsAppTemplate {
            $template = WhatsAppTemplate::create([
                'school_id' => $data->schoolId,
                'waba_id' => $data->wabaId,
                'notification_key' => $data->notificationKey,
                'meta_template_name' => $data->metaTemplateName,
                'category' => $data->category,
                'language' => $data->language,
                'header_type' => $data->headerType,
                'body_text' => $data->bodyText,
                'footer_text' => $data->footerText,
                'buttons' => $data->buttons,
                'submitted_at' => Carbon::now(),
                'review_status' => 'pending',
            ]);

            event(new TemplateSubmitted($template));

            return $template;
        });
    }
}
