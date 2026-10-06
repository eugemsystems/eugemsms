<?php

use Modules\Academic\Domain\Actions\AmendMarkAction;
use Modules\Academic\Domain\Actions\ApproveTermResultsAction;
use Modules\Academic\Domain\Actions\BuildResultsAnalyticsAction;
use Modules\Academic\Domain\Actions\ComputeTermResultsAction;
use Modules\Academic\Domain\Actions\ComputeTermSubjectResultsAction;
use Modules\Academic\Domain\Actions\CreateAssessmentAction;
use Modules\Academic\Domain\Actions\EnterMarkAction;
use Modules\Academic\Domain\Actions\GenerateReportCardsAction;
use Modules\Academic\Domain\Actions\GenerateTranscriptAction;
use Modules\Academic\Domain\Actions\PublishAssessmentAction;
use Modules\Academic\Domain\Actions\PublishReportCardsAction;
use Modules\Academic\Domain\Actions\RecomputeSubjectPositionsAction;
use Modules\Academic\Domain\Actions\RecomputeTermPositionsAction;
use Modules\Academic\Domain\Actions\SetTermResultCommentsAction;
use Modules\Academic\Domain\Actions\SubmitAssessmentMarksAction;
use Modules\Academic\Domain\DataObjects\AmendMarkData;
use Modules\Academic\Domain\DataObjects\ApproveTermResultsData;
use Modules\Academic\Domain\DataObjects\ComputeTermResultsData;
use Modules\Academic\Domain\DataObjects\ComputeTermSubjectResultsData;
use Modules\Academic\Domain\DataObjects\CreateAssessmentData;
use Modules\Academic\Domain\DataObjects\EnterMarkData;
use Modules\Academic\Domain\DataObjects\GenerateReportCardsData;
use Modules\Academic\Domain\DataObjects\GenerateTranscriptData;
use Modules\Academic\Domain\DataObjects\PublishAssessmentData;
use Modules\Academic\Domain\DataObjects\PublishReportCardsData;
use Modules\Academic\Domain\DataObjects\RecomputeSubjectPositionsData;
use Modules\Academic\Domain\DataObjects\RecomputeTermPositionsData;
use Modules\Academic\Domain\DataObjects\SetTermResultCommentsData;
use Modules\Academic\Domain\DataObjects\SubmitAssessmentMarksData;
use Modules\Academic\Domain\Exceptions\AssessmentWeightsIncompleteException;
use Modules\Academic\Models\ReportCardRun;
use Modules\Academic\Models\TermResult;
use Modules\Core\Domain\Actions\Settings\SetSettingValueAction;
use Modules\Core\Domain\DataObjects\Settings\SetSettingValueData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\Settings\SettingScope;
use Modules\Core\Models\Document;
use Modules\Finance\Domain\Actions\GrantReportGateOverrideAction;
use Modules\Finance\Domain\DataObjects\GrantReportGateOverrideData;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\FeeComponent;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\InvoiceLine;

/**
 * Book D ACA-05 report cards: approve, generate (with the fee gate), publish,
 * transcripts. Reuses `aca05Fixture()` etc. from `Aca05GradingAndResultsTest`,
 * so run the module directory.
 *
 * @return array<string, mixed>
 */
function rcFixture(float $examA = 90, float $examB = 40): array
{
    $f = aca05Fixture(courseworkWeightPercent: 0);
    $f['year']->update(['is_current' => true]);
    $types = aca05AssessmentTypes($f);
    $a = aca05Student($f, 'Chipo');
    $b = aca05Student($f, 'Farai');

    $exam = app(CreateAssessmentAction::class)->execute(new CreateAssessmentData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        assessmentTypeId: $types['examination'], subjectId: $f['subject']->id, title: 'End of Term Exam',
        maxMark: 100, weightPercent: 100, createdByUserId: $f['user']->id,
    ));

    app(EnterMarkAction::class)->execute(new EnterMarkData($exam->id, $a->id, $f['user']->id, rawMark: $examA));
    app(EnterMarkAction::class)->execute(new EnterMarkData($exam->id, $b->id, $f['user']->id, rawMark: $examB));
    app(SubmitAssessmentMarksAction::class)->execute(new SubmitAssessmentMarksData($exam->id, $f['user']->id));
    app(PublishAssessmentAction::class)->execute(new PublishAssessmentData($exam->id, $f['user']->id));

    foreach ([$a, $b] as $student) {
        app(ComputeTermSubjectResultsAction::class)->execute(new ComputeTermSubjectResultsData(
            studentId: $student->id, subjectId: $f['subject']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        ));
    }

    app(RecomputeSubjectPositionsAction::class)->execute(new RecomputeSubjectPositionsData($f['school']->id, $f['term']->id, $f['subject']->id));

    foreach ([$a, $b] as $student) {
        app(ComputeTermResultsAction::class)->execute(new ComputeTermResultsData($student->id, $f['term']->id));
    }

    app(RecomputeTermPositionsAction::class)->execute(new RecomputeTermPositionsData($f['school']->id, $f['term']->id, $f['class']->id));

    return $f + ['a' => $a, 'b' => $b, 'exam' => $exam];
}

/**
 * @param  array<string, mixed>  $f
 */
function rcGenerate(array $f): ReportCardRun
{
    return app(GenerateReportCardsAction::class)->execute(new GenerateReportCardsData($f['school']->id, $f['term']->id, $f['user']->id, classId: $f['class']->id));
}

/**
 * @param  array<string, mixed>  $f
 */
function rcWithholdForFees(array $f, $student, int $owedMinor = 34000): void
{
    $income = Account::factory()->for($f['school'])->income()->create();
    $debtor = Account::factory()->for($f['school'])->controlAccount('student')->create();
    $component = FeeComponent::factory()->for($f['school'])->create(['income_account_id' => $income->id, 'debtor_account_id' => $debtor->id, 'counts_toward_report_gate' => true]);
    $invoice = Invoice::factory()->for($f['school'])->create([
        'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id, 'student_id' => $student->id,
        'gross_minor' => $owedMinor, 'net_minor' => $owedMinor, 'balance_minor' => $owedMinor, 'currency' => 'USD',
    ]);
    InvoiceLine::factory()->for($f['school'])->create(['invoice_id' => $invoice->id, 'component_id' => $component->id, 'gross_minor' => $owedMinor, 'net_minor' => $owedMinor, 'currency' => 'USD']);

    app(SetSettingValueAction::class)->execute(new SetSettingValueData('finance.report_gate_enabled', SettingScope::School, $f['school']->id, true));
    app(SetSettingValueAction::class)->execute(new SetSettingValueData('finance.report_gate_threshold_minor', SettingScope::School, $f['school']->id, 10000));
    app(SetSettingValueAction::class)->execute(new SetSettingValueData('finance.report_gate_threshold_currency', SettingScope::School, $f['school']->id, 'USD'));
}

it('blocks results computation and names the subject when its assessment weights do not total 100% (AC-ACA-05-001)', function (): void {
    $f = aca05Fixture(courseworkWeightPercent: 0);
    $types = aca05AssessmentTypes($f);
    $student = aca05Student($f, 'Chipo');

    $exam = app(CreateAssessmentAction::class)->execute(new CreateAssessmentData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        assessmentTypeId: $types['examination'], subjectId: $f['subject']->id, title: 'Exam',
        maxMark: 100, weightPercent: 90, createdByUserId: $f['user']->id,
    ));
    app(EnterMarkAction::class)->execute(new EnterMarkData($exam->id, $student->id, $f['user']->id, rawMark: 70));
    app(SubmitAssessmentMarksAction::class)->execute(new SubmitAssessmentMarksData($exam->id, $f['user']->id));
    app(PublishAssessmentAction::class)->execute(new PublishAssessmentData($exam->id, $f['user']->id));

    expect(fn () => app(ComputeTermSubjectResultsAction::class)->execute(new ComputeTermSubjectResultsData(
        studentId: $student->id, subjectId: $f['subject']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
    )))->toThrow(AssessmentWeightsIncompleteException::class, 'short by 10%');
});

it('generates stored report cards for approved results, with the learner\'s own data and no unescaped markup', function (): void {
    $f = rcFixture();
    $f['a']->update(['first_name' => '<script>x</script>Chipo']);
    app(SetTermResultCommentsAction::class)->execute(new SetTermResultCommentsData(TermResult::where('student_id', $f['a']->id)->value('id'), 'Works <b>hard</b>', 'Well done'));

    $run = rcGenerate($f);
    expect($run->generated_count)->toBe(2)->and($run->failed_count)->toBe(0)->and($run->status)->toBe('completed');

    $result = TermResult::where('student_id', $f['a']->id)->firstOrFail();
    $document = Document::findOrFail($result->report_document_id);
    $html = Storage::disk('local')->get($document->file_path);

    expect($result->report_version)->toBe(1)
        ->and($html)->toContain('Chipo')->and($html)->toContain('90.0')
        ->and($html)->not->toContain('<script>')->and($html)->toContain('&lt;b&gt;hard&lt;/b&gt;');
});

it('only generates for approved results when moderation is required', function (): void {
    $f = rcFixture();
    app(SetSettingValueAction::class)->execute(new SetSettingValueData('academic.require_moderation', SettingScope::School, $f['school']->id, true));

    expect(fn () => rcGenerate($f))->toThrow(InvalidArgumentException::class, 'approved');

    $approved = app(ApproveTermResultsAction::class)->execute(new ApproveTermResultsData($f['term']->id, $f['user']->id, $f['class']->id));
    expect($approved)->toBe(2)->and(rcGenerate($f)->generated_count)->toBe(2);
});

it('generates but withholds a learner over the fee gate, keeps them withheld at publication, then releases the stored card on override (AC-ACA-05-004/005)', function (): void {
    $f = rcFixture();
    rcWithholdForFees($f, $f['b']);

    $run = rcGenerate($f);
    expect($run->generated_count)->toBe(2)->and($run->withheld_count)->toBe(1);

    $withheld = TermResult::where('student_id', $f['b']->id)->firstOrFail();
    expect($withheld->status)->toBe('withheld')->and($withheld->withheld_reason)->toBe('fee_balance')->and($withheld->report_document_id)->not->toBeNull();
    $documentId = $withheld->report_document_id;

    $first = app(PublishReportCardsAction::class)->execute(new PublishReportCardsData($f['school']->id, $f['term']->id, $f['user']->id, classId: $f['class']->id));
    expect($first->published)->toBe(1)->and($first->stillWithheld)->toBe(1)
        ->and(TermResult::where('student_id', $f['a']->id)->value('status'))->toBe('published')
        ->and($withheld->fresh()->status)->toBe('withheld');

    app(GrantReportGateOverrideAction::class)->execute(new GrantReportGateOverrideData($f['school']->id, $f['b']->id, $f['term']->id, 'Hardship approved by the bursar.', $f['user']->id));

    $second = app(PublishReportCardsAction::class)->execute(new PublishReportCardsData($f['school']->id, $f['term']->id, $f['user']->id, classId: $f['class']->id));
    expect($second->releasedFromWithheld)->toBe(1)
        ->and($withheld->fresh()->status)->toBe('published')
        ->and($withheld->fresh()->report_document_id)->toBe($documentId)
        ->and($withheld->fresh()->report_version)->toBe(1);
});

it('does not publish a result that has no stored report card', function (): void {
    $f = rcFixture();

    $result = app(PublishReportCardsAction::class)->execute(new PublishReportCardsData($f['school']->id, $f['term']->id, $f['user']->id, classId: $f['class']->id));

    expect($result->published)->toBe(0)->and($result->notGenerated)->toBe(2);
});

it('keeps a published result published when it is recomputed, and locks its comments', function (): void {
    $f = rcFixture();
    rcGenerate($f);
    app(PublishReportCardsAction::class)->execute(new PublishReportCardsData($f['school']->id, $f['term']->id, $f['user']->id, classId: $f['class']->id));

    app(ComputeTermResultsAction::class)->execute(new ComputeTermResultsData($f['a']->id, $f['term']->id));
    $result = TermResult::where('student_id', $f['a']->id)->firstOrFail();

    expect($result->status)->toBe('published')
        ->and(fn () => app(SetTermResultCommentsAction::class)->execute(new SetTermResultCommentsData($result->id, 'Too late')))->toThrow(InvalidStateTransitionException::class);
});

it('regenerates a published report card as a new version when its mark is amended with approval (AC-ACA-05-003)', function (): void {
    $f = rcFixture();
    rcGenerate($f);
    app(PublishReportCardsAction::class)->execute(new PublishReportCardsData($f['school']->id, $f['term']->id, $f['user']->id, classId: $f['class']->id));

    $before = TermResult::where('student_id', $f['a']->id)->firstOrFail();
    $oldDocument = $before->report_document_id;

    app(AmendMarkAction::class)->execute(new AmendMarkData(
        assessmentId: $f['exam']->id, studentId: $f['a']->id, changedByUserId: $f['user']->id,
        changeReason: 'Marking error found on moderation.', rawMark: 20, approved: true,
    ));

    $after = TermResult::where('student_id', $f['a']->id)->firstOrFail();
    $html = Storage::disk('local')->get(Document::findOrFail($after->report_document_id)->file_path);

    expect($after->report_version)->toBe(2)->and($after->report_document_id)->not->toBe($oldDocument)->and($after->status)->toBe('published')
        ->and($html)->toContain('20.0')
        ->and(Document::find($oldDocument))->not->toBeNull()
        ->and(TermResult::where('student_id', $f['b']->id)->value('class_position'))->toBe(1);
    // B overtook A, so B's published card moved too and was regenerated.
    expect(TermResult::where('student_id', $f['b']->id)->value('report_version'))->toBe(2);
});

it('builds a verifiable transcript from published results only', function (): void {
    $f = rcFixture();

    expect(fn () => app(GenerateTranscriptAction::class)->execute(new GenerateTranscriptData($f['a']->id, $f['user']->id)))->toThrow(InvalidArgumentException::class);

    rcGenerate($f);
    app(PublishReportCardsAction::class)->execute(new PublishReportCardsData($f['school']->id, $f['term']->id, $f['user']->id, classId: $f['class']->id));

    $document = app(GenerateTranscriptAction::class)->execute(new GenerateTranscriptData($f['a']->id, $f['user']->id));
    $html = Storage::disk('local')->get($document->file_path);

    expect($document->verification_code)->not->toBeNull()->and($html)->toContain('Academic transcript')->and($html)->toContain('90.0');
});

it('rolls results up by subject, class and trend', function (): void {
    $f = rcFixture();

    $data = app(BuildResultsAnalyticsAction::class)->execute($f['term']->id);

    expect($data['subjects'])->toHaveCount(1)
        ->and($data['subjects'][0]['mean'])->toBe(65.0)
        ->and($data['subjects'][0]['pass_rate'])->toBe(50.0)
        ->and($data['subjects'][0]['distribution'][9])->toBe(1)
        ->and($data['classes'][0]['mean'])->toBe(65.0)
        ->and($data['trend'])->toHaveCount(1);
});
