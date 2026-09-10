<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Models\AttendanceSession;
use Modules\Compliance\Domain\DataObjects\GenerateInspectionPackData;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Files\UploadFileAction;
use Modules\Core\Domain\DataObjects\Files\UploadFileData;
use Modules\Core\Models\File;
use Modules\People\Models\EstablishmentPost;
use Modules\People\Models\Staff;

/**
 * ACT-GenerateInspectionPack (Book H3 CMP-02 §3 ⭐/BR-CMP-02-005
 * (AC-CMP-02-003)). Assembles attendance registers, staff records and
 * statutory documents into one JSON document — mirroring Book H3
 * FIN-12's own `GenerateClosePackAction` pattern (assemble, upload,
 * link back). Attendance registers come from `ACA-04`
 * (`AttendanceSession`), not `OPS-02` as the spec originally named it
 * — see the correction on `BR-CMP-02-001` in the spec file itself.
 * `policy_acknowledgements` is explicitly `null` here: CMP-04 (Policy,
 * Document Register & Retention), the module that would define what a
 * policy acknowledgement even is, has not been built yet in this book
 * — a genuine forward dependency, not an oversight.
 */
final class GenerateInspectionPackAction extends Action
{
    public function __construct(
        private readonly UploadFileAction $uploadFile,
    ) {}

    public function execute(GenerateInspectionPackData $data): File
    {
        $attendance = AttendanceSession::where('school_id', $data->schoolId)
            ->whereDate('session_date', '>=', $data->periodStart->toDateString())
            ->whereDate('session_date', '<=', $data->periodEnd->toDateString())
            ->get()
            ->map(fn (AttendanceSession $session): array => [
                'session_date' => $session->session_date->toDateString(),
                'class_id' => $session->class_id,
                'expected_count' => $session->expected_count,
                'present_count' => $session->present_count,
                'absent_count' => $session->absent_count,
                'late_count' => $session->late_count,
                'status' => $session->status,
            ])->values()->all();

        $staff = Staff::where('school_id', $data->schoolId)->where('status', 'active')->get()
            ->map(fn (Staff $member): array => [
                'staff_number' => $member->staff_number,
                'name' => trim("{$member->first_name} {$member->last_name}"),
                'staff_category' => $member->staff_category,
                'is_teaching' => $member->is_teaching,
                'teacher_registration_no' => $member->teacher_registration_no,
                'post_id' => $member->post_id,
            ])->values()->all();

        $posts = EstablishmentPost::where('school_id', $data->schoolId)->where('is_active', true)->get()
            ->map(fn (EstablishmentPost $post): array => [
                'title' => $post->title,
                'approved_count' => $post->approved_count,
                'filled_count' => $post->filled_count,
            ])->values()->all();

        $statutoryDocuments = File::where('school_id', $data->schoolId)
            ->whereIn('category', ['birth_certificate', 'national_registration', 'staff_contract', 'police_clearance', 'qualification'])
            ->get()
            ->map(fn (File $file): array => [
                'category' => $file->category,
                'original_name' => $file->original_name,
                'attachable_type' => $file->attachable_type,
                'attachable_id' => $file->attachable_id,
            ])->values()->all();

        $payload = [
            'generated_at' => Carbon::now()->toIso8601String(),
            'period_start' => $data->periodStart->toDateString(),
            'period_end' => $data->periodEnd->toDateString(),
            'attendance_registers' => $attendance,
            'staff_records' => $staff,
            'establishment_posts' => $posts,
            'statutory_documents' => $statutoryDocuments,
            'policy_acknowledgements' => null,
        ];

        return $this->transaction(fn (): File => $this->uploadFile->execute(new UploadFileData(
            schoolId: $data->schoolId,
            category: 'inspection_pack',
            contents: json_encode($payload, JSON_PRETTY_PRINT) ?: '',
            originalName: "inspection-pack-{$data->periodStart->toDateString()}-{$data->periodEnd->toDateString()}.json",
            uploadedByUserId: $data->generatedByUserId,
        )));
    }
}
