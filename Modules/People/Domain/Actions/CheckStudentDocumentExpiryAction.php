<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\People\Domain\Events\StudentDocumentExpiring;
use Modules\People\Models\StudentDocument;

/**
 * ACT-CheckStudentDocumentExpiry (Book C PPL-01 §7/BR-PPL-01-021). Lists
 * learner documents that have expired or fall inside the widest warning window
 * (default 90, 30, 7 days) and raises `StudentDocumentExpiring` on the days that
 * match a threshold exactly — once per threshold, not every night.
 */
final class CheckStudentDocumentExpiryAction extends Action
{
    protected bool $transactional = false;

    public function __construct(private readonly SettingResolver $settings) {}

    /**
     * @return array<int, array{document: StudentDocument, daysRemaining: int}>
     */
    public function execute(int $schoolId, ?Carbon $asOf = null): array
    {
        $asOf = ($asOf ?? Carbon::now())->copy()->startOfDay();

        /** @var array<int, int> $thresholds */
        $thresholds = (array) $this->settings->get('students.document_expiry_warning_days', new ScopeChain(schoolId: $schoolId));
        $thresholds = $thresholds === [] ? [90, 30, 7] : $thresholds;

        $results = [];

        foreach (StudentDocument::query()->where('school_id', $schoolId)->whereNotNull('expires_on')->where('expires_on', '<=', $asOf->copy()->addDays(max($thresholds))->toDateString())->orderBy('expires_on')->get() as $document) {
            $daysRemaining = (int) round($asOf->diffInDays($document->expires_on->copy()->startOfDay(), absolute: false));
            $results[] = ['document' => $document, 'daysRemaining' => $daysRemaining];

            if (in_array($daysRemaining, $thresholds, true) || $daysRemaining === 0) {
                event(new StudentDocumentExpiring($document, $daysRemaining));
            }
        }

        return $results;
    }
}
