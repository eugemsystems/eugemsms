<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Documents\AllocateNumberAction;
use Modules\Core\Domain\DataObjects\Documents\AllocateNumberData;
use Modules\Operations\Domain\DataObjects\ReportFaultData;
use Modules\Operations\Domain\Events\SafetyFaultReported;
use Modules\Operations\Models\FaultReport;

/**
 * ACT-ReportFault (Book H2 OPS-02 §6 ⭐/BR-OPS-02-001/002/
 * AC-OPS-02-001). Deliberately the lowest-friction write in this
 * module — location, description and severity are the only required
 * facts, matching BR-OPS-02-001's own "and nothing else". A
 * safety-affecting report fires its alert here, at creation, rather
 * than waiting for triage to notice it.
 */
final class ReportFaultAction extends Action
{
    public function __construct(
        private readonly AllocateNumberAction $allocateNumber,
    ) {}

    public function execute(ReportFaultData $data): FaultReport
    {
        $number = $this->allocateNumber->execute(new AllocateNumberData(
            schoolId: $data->schoolId,
            documentType: 'fault_report',
            allocatedByUserId: $data->reportedByUserId,
        ));

        return $this->transaction(function () use ($data, $number): FaultReport {
            $report = FaultReport::create([
                'school_id' => $data->schoolId,
                'term_id' => $data->termId,
                'report_number' => $number->formatted_number,
                'maintenance_asset_id' => $data->maintenanceAssetId,
                'location' => $data->location,
                'category' => $data->category,
                'description' => $data->description,
                'photo_file_ids' => $data->photoFileIds,
                'severity' => $data->severity,
                'affects_safety' => $data->affectsSafety,
                'affects_teaching' => $data->affectsTeaching,
                'reported_by' => $data->reportedByUserId,
                'reported_at' => now(),
                'source_type' => $data->sourceType,
                'source_id' => $data->sourceId,
                'status' => 'reported',
            ]);

            if ($report->affects_safety) {
                event(new SafetyFaultReported($report));
            }

            return $report;
        });
    }
}
