<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Modules\Boarding\Domain\DataObjects\RecordRoomInspectionData;
use Modules\Boarding\Domain\Events\InspectionRecorded;
use Modules\Boarding\Models\RoomInspection;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-RecordRoomInspection (Book F BRD-01 §4/BR-BRD-01-011).
 */
final class RecordRoomInspectionAction extends Action
{
    public function execute(RecordRoomInspectionData $data): RoomInspection
    {
        $totalScore = array_sum($data->criteriaScores);

        return $this->transaction(function () use ($data, $totalScore): RoomInspection {
            $inspection = RoomInspection::create([
                'school_id' => $data->schoolId,
                'term_id' => $data->termId,
                'room_id' => $data->roomId,
                'inspection_date' => $data->inspectionDate->toDateString(),
                'inspection_type' => $data->inspectionType,
                'criteria_scores' => $data->criteriaScores,
                'total_score' => $totalScore,
                'max_score' => $data->maxScore,
                'grade' => $this->grade($totalScore, $data->maxScore),
                'findings' => $data->findings,
                'photo_file_ids' => $data->photoFileIds,
                'inspector_staff_id' => $data->inspectorStaffId,
                'follow_up_required' => $data->followUpRequired,
            ]);

            event(new InspectionRecorded($inspection));

            return $inspection;
        });
    }

    private function grade(float $totalScore, float $maxScore): ?string
    {
        if ($maxScore <= 0.0) {
            return null;
        }

        $percent = ($totalScore / $maxScore) * 100;

        return match (true) {
            $percent >= 90 => 'excellent',
            $percent >= 75 => 'good',
            $percent >= 50 => 'satisfactory',
            default => 'poor',
        };
    }
}
