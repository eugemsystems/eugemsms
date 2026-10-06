<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Comms\Models\Newsletter;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Models\Guardian;
use Modules\People\Models\Staff;
use Throwable;

/**
 * ACT-SendNewsletter (Book I COM-06 §2/§4). Fans an issue out by email through the
 * CORE-09 notification bus, one message per distinct address: active guardians with
 * an email on file and active staff for a whole-school issue, staff only for a staff
 * issue. Narrower audiences (a section or a level) are refused rather than guessed —
 * a newsletter does not record which section or level it targets.
 *
 * Messaging never blocks the operation it attaches to: a recipient the bus cannot
 * reach is counted as failed and the issue is still marked sent. The bus's own dedupe
 * (one message per recipient per issue) makes a second call harmless; the related
 * type carries the recipient type because the bus's dedupe key is recipient id +
 * related type + related id, and a guardian and a staff member can share an id. `content_html`
 * is turned to plain text for the message body; the HTML archive stays on the row.
 */
final class SendNewsletterAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    /**
     * @return array{sent: int, failed: int, newsletter: Newsletter}
     */
    public function execute(int $newsletterId): array
    {
        $newsletter = Newsletter::query()->findOrFail($newsletterId);

        if (! in_array($newsletter->status, ['draft', 'scheduled'], true)) {
            throw new InvalidStateTransitionException('Only a draft or scheduled issue can be sent.');
        }

        if (! in_array($newsletter->audience_scope, ['whole_school', 'staff'], true)) {
            throw new InvalidStateTransitionException('Only whole-school and staff newsletters can be sent; a section or level audience is not recorded on the issue.');
        }

        $text = trim((string) preg_replace('/\n{3,}/', "\n\n", html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '</li>'], "\n", $newsletter->content_html)))));
        $sent = 0;
        $failed = 0;

        foreach ($this->recipients($newsletter) as $email => [$type, $id]) {
            try {
                $this->dispatchNotification->execute(new DispatchNotificationData(
                    schoolId: $newsletter->school_id,
                    notificationKey: 'comms.newsletter',
                    recipientType: $type,
                    addresses: ['email' => $email],
                    context: ['newsletter' => ['title' => $newsletter->title, 'issue_number' => $newsletter->issue_number, 'text' => $text]],
                    recipientId: $id,
                    channel: 'email',
                    relatedType: "newsletter_{$type}",
                    relatedId: $newsletter->id,
                    dedupeWindowMinutes: 525600,
                ));
                $sent++;
            } catch (Throwable) {
                $failed++;
            }
        }

        $newsletter->update(['status' => 'sent', 'sent_at' => Carbon::now()]);

        return ['sent' => $sent, 'failed' => $failed, 'newsletter' => $newsletter];
    }

    /**
     * @return array<string, array{0: string, 1: int}> lower-cased email => [recipient type, id]
     */
    private function recipients(Newsletter $newsletter): array
    {
        $recipients = [];

        Staff::query()->where('status', 'active')->get(['id', 'work_email', 'personal_email'])
            ->each(function (Staff $staff) use (&$recipients): void {
                $email = $staff->work_email ?? $staff->personal_email;

                if ($email !== null && $email !== '') {
                    $recipients[strtolower($email)] ??= ['staff', $staff->id];
                }
            });

        if ($newsletter->audience_scope === 'whole_school') {
            Guardian::query()->where('status', 'active')->whereNotNull('email')->get(['id', 'email'])
                ->each(function (Guardian $guardian) use (&$recipients): void {
                    $recipients[strtolower((string) $guardian->email)] ??= ['guardian', $guardian->id];
                });
        }

        return $recipients;
    }
}
