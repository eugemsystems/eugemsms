<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\People\Models\Application;
use Modules\People\Models\Enquiry;

/**
 * ACT-BuildAdmissionsFunnel (Book C PPL-02 §5). Counts how many enquiries and
 * applications reached each step, with the reasons enquiries were lost. Read
 * only; optionally for one intake.
 */
final class BuildAdmissionsFunnelAction extends Action
{
    protected bool $transactional = false;

    /**
     * @return array{enquiries: int, applied: int, offered: int, accepted: int, enrolled: int, lost: array<string, int>, byStage: array<string, int>}
     */
    public function execute(?int $intakeId = null): array
    {
        $enquiries = Enquiry::query()->when($intakeId !== null, fn ($q) => $q->where('intake_id', $intakeId));
        $applications = Application::query()->when($intakeId !== null, fn ($q) => $q->where('intake_id', $intakeId));

        return [
            'enquiries' => (clone $enquiries)->count(),
            'applied' => (clone $applications)->whereNotIn('status', ['draft', 'fee_pending'])->count(),
            'offered' => (clone $applications)->whereNotNull('offer_made_at')->count(),
            'accepted' => (clone $applications)->whereNotNull('accepted_at')->count(),
            'enrolled' => (clone $applications)->whereNotNull('student_id')->count(),
            'lost' => (clone $enquiries)->where('stage', 'lost')->selectRaw('lost_reason, count(*) as total')->groupBy('lost_reason')->pluck('total', 'lost_reason')->map(fn ($n): int => (int) $n)->all(),
            'byStage' => (clone $enquiries)->selectRaw('stage, count(*) as total')->groupBy('stage')->pluck('total', 'stage')->map(fn ($n): int => (int) $n)->all(),
        ];
    }
}
