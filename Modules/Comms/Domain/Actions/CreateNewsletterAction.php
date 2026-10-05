<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Modules\Comms\Domain\DataObjects\CreateNewsletterData;
use Modules\Comms\Models\Newsletter;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateNewsletter (Book I COM-06 §2). The backend pass built the
 * `newsletters` table and model but no way to create one; this is the
 * admin-UI pass's gap-filling Action. A newsletter is saved as a
 * `draft`, or `scheduled` when `scheduledFor` is given — it never
 * becomes `sent` here: fanning an issue out to an audience is not
 * built (CORE-09's dispatch takes one addressable recipient, not a
 * scope), and `sent_at`/`archive_file_id` are only ever set by that
 * future sender.
 *
 * `content_html` is stored with a conservative tag allowlist applied —
 * a first line of defence only; whatever renders it later must still
 * sanitise.
 */
final class CreateNewsletterAction extends Action
{
    private const string ALLOWED_TAGS = '<p><br><strong><em><b><i><u><ul><ol><li><a><h1><h2><h3><blockquote>';

    public function execute(CreateNewsletterData $data): Newsletter
    {
        return $this->transaction(fn (): Newsletter => Newsletter::create([
            'school_id' => $data->schoolId,
            'issue_number' => $data->issueNumber,
            'title' => $data->title,
            'content_html' => strip_tags($data->contentHtml, self::ALLOWED_TAGS),
            'audience_scope' => $data->audienceScope,
            'scheduled_for' => $data->scheduledFor,
            'status' => $data->scheduledFor !== null ? 'scheduled' : 'draft',
        ]));
    }
}
