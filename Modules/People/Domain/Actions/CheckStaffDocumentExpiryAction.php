<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\People\Domain\Events\StaffDocumentExpiring;
use Modules\People\Models\StaffDocument;

/**
 * ACT-CheckStaffDocumentExpiry (Book C PPL-04 §4/BR-PPL-04-018,
 * AC-PPL-04-006). Not wired to any scheduler in this pass — called
 * on demand (e.g. from a future daily job, or directly by the
 * compliance dashboard screen) — every document within the widest
 * configured alert window is returned and re-fires
 * `StaffDocumentExpiring` on each call; there's no "already alerted
 * today" tracking column to dedupe against yet.
 */
final class CheckStaffDocumentExpiryAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    /**
     * @return array<int, array{document: StaffDocument, daysRemaining: int, isComplianceCritical: bool}>
     */
    public function execute(int $schoolId, ?Carbon $asOf = null): array
    {
        $asOf = $asOf ?? Carbon::now();

        /** @var array<int, int> $thresholds */
        $thresholds = (array) $this->settings->get('staff.document_expiry_warning_days', new ScopeChain(schoolId: $schoolId));
        $widestThreshold = $thresholds === [] ? 90 : max($thresholds);

        $documents = StaffDocument::where('school_id', $schoolId)
            ->whereNotNull('expires_on')
            ->where('expires_on', '<=', $asOf->copy()->addDays($widestThreshold)->toDateString())
            ->orderBy('expires_on')
            ->get();

        $results = [];

        foreach ($documents as $document) {
            // round() before casting to int — diffInDays() can return a
            // value a hair off the true integer from sub-second
            // precision (see the Payroll module's own recorded rule on
            // this exact Carbon gotcha).
            $daysRemaining = (int) round($asOf->startOfDay()->diffInDays($document->expires_on->copy()->startOfDay(), absolute: false));
            $isComplianceCritical = $document->isComplianceCritical();

            $results[] = ['document' => $document, 'daysRemaining' => $daysRemaining, 'isComplianceCritical' => $isComplianceCritical];

            if ($isComplianceCritical) {
                event(new StaffDocumentExpiring($document, $daysRemaining));
            }
        }

        return $results;
    }
}
