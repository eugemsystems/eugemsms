<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Boarding\Models\BedAllocation;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Files\UploadFileAction;
use Modules\Core\Domain\DataObjects\Files\UploadFileData;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Intelligence\Models\BoardPack;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;
use Modules\Reporting\Domain\Actions\GenerateCollectionReportAction;
use Modules\Reporting\Domain\Actions\GenerateIncomeStatementAction;
use Modules\Reporting\Domain\Actions\GenerateKeyFinancialRatiosAction;
use Modules\Reporting\Domain\DataObjects\GenerateIncomeStatementData;
use Modules\Reporting\Domain\DataObjects\GenerateKeyFinancialRatiosData;
use Modules\Reporting\Domain\DataObjects\GenerateManagementReportData;

/**
 * ACT-GenerateBoardPack (Book J INT-02 §3/BR-INT-02-006 (AC-INT-02-003),
 * also satisfying Book H3 FIN-12 §6's own `Reports\Board\Pack`/
 * BR-FIN-12-015 — the same "board reporting pack" concept named twice
 * across two books' specs, not two separate features; see
 * `.ai/rules/financial-close.md` for why no second screen was built).
 * The financial section is `FIN-12`'s own real `GenerateIncomeStatementAction`
 * output, embedded UNMODIFIED — this action never recomputes a
 * financial figure. `collection_rate` is likewise `FIN-12`'s own real
 * `GenerateCollectionReportAction`, rolled up into one whole-school
 * figure. Follows the exact "content-hashed JSON, no bespoke PDF
 * renderer" pattern `Modules\Reporting\Domain\Actions\GenerateClosePackAction`
 * already established for the same reason (a real, complete document;
 * a simpler presentation format).
 *
 * **Honest scope boundary.** `enrolment`, `financial`, `staffing`,
 * `boarding`, `collection_rate`, and `key_ratios` are real, resolvable
 * sections. `academic` (outcomes) and `risk_summary` (only meaningful
 * once `INT-03` exists) are named in the spec's own `sections_included`
 * example but have no resolver in this pass — requesting either
 * throws a clear exception rather than fabricating a section with no
 * real data behind it.
 *
 * **Gap closed (2026-10-08, BR-FIN-12-015, user-approved ratio
 * selection):** `key_ratios` — operating margin, staff cost %,
 * total-assets-to-total-liabilities (not literally "current ratio";
 * see `GenerateKeyFinancialRatiosAction`'s own docblock for why), and
 * the same collection rate `collection_rate` already surfaces. The
 * spec names no specific ratio list, so this is a deliberate,
 * documented choice of four conventional, GL-derived ratios, not a
 * guess at an unspecified figure.
 */
final class GenerateBoardPackAction extends Action
{
    private const array SUPPORTED_SECTIONS = ['enrolment', 'financial', 'staffing', 'boarding', 'collection_rate', 'key_ratios'];

    public function __construct(
        private readonly GenerateIncomeStatementAction $generateIncomeStatement,
        private readonly GenerateCollectionReportAction $generateCollectionReport,
        private readonly GenerateKeyFinancialRatiosAction $generateKeyFinancialRatios,
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
                'collection_rate' => $this->collectionRateSection($schoolId, $term),
                'key_ratios' => $this->keyRatiosSection($schoolId, $term),
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

    /**
     * @return array<string, mixed>
     */
    private function collectionRateSection(int $schoolId, Term $term): array
    {
        $currency = School::findOrFail($schoolId)->base_currency;

        $result = $this->generateCollectionReport->execute(new GenerateManagementReportData(
            schoolId: $schoolId,
            periodStart: $term->starts_on,
            periodEnd: $term->ends_on,
            currency: $currency,
        ));

        return [
            'currency' => $currency,
            'billed_minor' => $result['total_billed_minor'],
            'paid_minor' => $result['total_paid_minor'],
            'rate_percent' => $result['rate_percent'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function keyRatiosSection(int $schoolId, Term $term): array
    {
        $currency = School::findOrFail($schoolId)->base_currency;

        return $this->generateKeyFinancialRatios->execute(new GenerateKeyFinancialRatiosData(
            schoolId: $schoolId,
            periodStart: $term->starts_on,
            periodEnd: $term->ends_on,
            currency: $currency,
        ));
    }
}
