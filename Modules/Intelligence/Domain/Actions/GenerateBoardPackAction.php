<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Boarding\Models\BedAllocation;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Files\UploadFileAction;
use Modules\Core\Domain\DataObjects\Files\UploadFileData;
use Modules\Core\Models\Term;
use Modules\Intelligence\Models\BoardPack;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;
use Modules\Reporting\Domain\Actions\GenerateIncomeStatementAction;
use Modules\Reporting\Domain\DataObjects\GenerateIncomeStatementData;

/**
 * ACT-GenerateBoardPack (Book J INT-02 §3/BR-INT-02-006
 * (AC-INT-02-003)). The financial section is `FIN-12`'s own real
 * `GenerateIncomeStatementAction` output, embedded UNMODIFIED — this
 * action never recomputes a financial figure. Follows the exact
 * "content-hashed JSON, no bespoke PDF renderer" pattern
 * `Modules\Reporting\Domain\Actions\GenerateClosePackAction` already
 * established for the same reason (a real, complete document; a
 * simpler presentation format).
 *
 * **Honest scope boundary.** `enrolment`, `financial`, `staffing`,
 * and `boarding` are real, resolvable sections. `academic` (outcomes)
 * and `risk_summary` (only meaningful once `INT-03` exists) are named
 * in the spec's own `sections_included` example but have no resolver
 * in this pass — requesting either throws a clear exception rather
 * than fabricating a section with no real data behind it.
 */
final class GenerateBoardPackAction extends Action
{
    private const array SUPPORTED_SECTIONS = ['enrolment', 'financial', 'staffing', 'boarding'];

    public function __construct(
        private readonly GenerateIncomeStatementAction $generateIncomeStatement,
        private readonly UploadFileAction $uploadFile,
    ) {}

    /**
     * @param  array<int, string>  $sectionsIncluded
     */
    public function execute(int $schoolId, int $termId, array $sectionsIncluded, int $generatedByUserId): BoardPack
    {
        foreach ($sectionsIncluded as $section) {
            if (! in_array($section, self::SUPPORTED_SECTIONS, true)) {
                throw new InvalidArgumentException("Board pack section '{$section}' has no resolver in this pass.");
            }
        }

        $term = Term::findOrFail($termId);
        $sections = [];

        foreach ($sectionsIncluded as $section) {
            $sections[$section] = match ($section) {
                'enrolment' => ['active_students' => Student::where('school_id', $schoolId)->where('status', 'active')->count()],
                'financial' => $this->financialSection($schoolId, $term),
                'staffing' => ['active_staff' => Staff::where('school_id', $schoolId)->where('status', 'active')->count()],
                'boarding' => ['confirmed_bed_allocations' => BedAllocation::where('school_id', $schoolId)->where('status', 'confirmed')->count()],
            };
        }

        $pack = [
            'school_id' => $schoolId,
            'term' => ['id' => $term->id, 'name' => $term->name],
            'sections' => $sections,
            'generated_by' => $generatedByUserId,
            'generated_at' => Carbon::now()->toIso8601String(),
        ];

        $file = $this->uploadFile->execute(new UploadFileData(
            schoolId: $schoolId,
            category: 'board_pack',
            contents: json_encode($pack, JSON_PRETTY_PRINT) ?: '{}',
            originalName: "board-pack-{$term->id}-".Carbon::now()->format('Ymd').'.json',
            uploadedByUserId: $generatedByUserId,
        ));

        return $this->transaction(fn (): BoardPack => BoardPack::create([
            'school_id' => $schoolId,
            'term_id' => $termId,
            'sections_included' => $sectionsIncluded,
            'document_id' => $file->id,
            'generated_by' => $generatedByUserId,
            'generated_at' => Carbon::now(),
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function financialSection(int $schoolId, Term $term): array
    {
        $result = $this->generateIncomeStatement->execute(new GenerateIncomeStatementData(
            schoolId: $schoolId,
            periodStart: $term->starts_on,
            periodEnd: $term->ends_on,
        ));

        return ['lines' => $result->lines, 'net_minor' => $result->netMinor];
    }
}
