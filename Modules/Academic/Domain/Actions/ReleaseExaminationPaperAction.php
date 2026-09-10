<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\ReleaseExaminationPaperData;
use Modules\Academic\Domain\Exceptions\PaperReleaseNotYetDueException;
use Modules\Academic\Models\ExaminationPaper;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Audit\RecordDataAccessAction;
use Modules\Core\Domain\Actions\Audit\RecordSecurityEventAction;
use Modules\Core\Domain\DataObjects\Audit\RecordDataAccessData;
use Modules\Core\Domain\DataObjects\Audit\RecordSecurityEventData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Models\DataAccessLogEntry;

/**
 * ACT-ReleaseExaminationPaper (Book E ACA-07 §3 ⭐⭐/AC-ACA-07-001/002).
 * The one enforceable control this pass builds in full: `release_at`
 * checked server-side with **no override parameter anywhere in this
 * class** — not for a Head, not for a Super Admin, not via any flag
 * on `ReleaseExaminationPaperData`. Every call is logged through
 * Core's existing `RecordDataAccessAction`
 * (`data_access_log`/BR-CORE-08-009), and a caller over
 * `exams.paper_max_downloads_per_user` raises a security event.
 *
 * Deliberately deferred — real infrastructure this pass does not
 * build: per-session file encryption at rest, and visible watermarking
 * of the downloaded file naming the requesting user. Both need
 * `CORE-10` file-storage capabilities (an encryption-at-rest key
 * scheme, a PDF/image watermarking library) that do not exist yet in
 * this codebase. The access-logged, time-locked gate below is real
 * and is what `AC-ACA-07-001` actually tests; watermarking is a
 * rendering step downstream of it.
 */
final class ReleaseExaminationPaperAction extends Action
{
    public function __construct(
        private readonly RecordDataAccessAction $recordDataAccess,
        private readonly RecordSecurityEventAction $recordSecurityEvent,
        private readonly SettingResolver $settings,
    ) {}

    public function execute(ReleaseExaminationPaperData $data): ExaminationPaper
    {
        $paper = ExaminationPaper::findOrFail($data->paperId);

        if (! in_array($paper->status, ['sealed', 'released'], true)) {
            throw new InvalidStateTransitionException(
                "Paper #{$paper->id} must be sealed before it can be released (currently {$paper->status}).",
                ['paper_id' => $paper->id, 'status' => $paper->status],
            );
        }

        if ($paper->release_at === null || Carbon::now()->lessThan($paper->release_at)) {
            throw PaperReleaseNotYetDueException::forPaper($paper->id, (string) $paper->release_at);
        }

        return $this->transaction(function () use ($paper, $data): ExaminationPaper {
            if ($paper->status === 'sealed') {
                $paper->update([
                    'released_at' => Carbon::now(),
                    'released_by' => $data->requestedByUserId,
                    'status' => 'released',
                ]);
            }

            $this->recordDataAccess->execute(new RecordDataAccessData(
                schoolId: $paper->school_id,
                userId: $data->requestedByUserId,
                accessType: 'download',
                resourceType: 'examination_paper',
                resourceId: $paper->id,
                recordCount: 1,
                purpose: 'exam_paper_release',
                ip: $data->ip,
            ));

            $this->enforceDownloadLimit($paper, $data->requestedByUserId, $data->ip);

            return $paper;
        });
    }

    private function enforceDownloadLimit(ExaminationPaper $paper, int $userId, ?string $ip): void
    {
        $limit = (int) $this->settings->get('exams.paper_max_downloads_per_user', new ScopeChain(schoolId: $paper->school_id));

        $downloads = DataAccessLogEntry::query()
            ->where('school_id', $paper->school_id)
            ->where('user_id', $userId)
            ->where('resource_type', 'examination_paper')
            ->where('resource_id', $paper->id)
            ->count();

        if ($downloads > $limit) {
            $this->recordSecurityEvent->execute(new RecordSecurityEventData(
                eventType: 'exam_paper_download_limit_exceeded',
                severity: 'warning',
                description: "User #{$userId} downloaded paper #{$paper->id} {$downloads} times, exceeding the {$limit}-download limit.",
                schoolId: $paper->school_id,
                userId: $userId,
                context: ['paper_id' => $paper->id, 'downloads' => $downloads, 'limit' => $limit],
                ip: $ip,
            ));
        }
    }
}
