<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Academic\Domain\Actions\PublishReportCardsAction;
use Modules\Academic\Domain\Actions\SetTermSubjectResultCommentAction;
use Modules\Academic\Domain\DataObjects\PublishReportCardsData;
use Modules\Academic\Domain\DataObjects\SetTermSubjectResultCommentData;
use Modules\Academic\Livewire\ReportCards\Publish;
use Modules\Academic\Livewire\ReportCards\Run;
use Modules\Academic\Livewire\ReportCards\Withheld;
use Modules\Academic\Livewire\Results\Analytics;
use Modules\Academic\Livewire\Results\Review;
use Modules\Academic\Livewire\Results\Transcripts;
use Modules\Academic\Models\ReportCardRun;
use Modules\Academic\Models\TermResult;
use Modules\Academic\Models\TermSubjectResult;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Models\Document;
use Modules\Core\Models\Permission;
use Modules\Finance\Models\ReportGateOverride;

/**
 * Book D ACA-05 report-card screens. Reuses `rcFixture()` from
 * `Aca05ReportCardsTest`, so run the module directory.
 *
 * @param  array<string, mixed>  $f
 */
function rcUser(array $f, string ...$permissionNames): User
{
    $user = User::factory()->create();
    $user->schools()->attach($f['school'], ['status' => 'active']);

    $grants = array_map(function (string $name): PermissionGrantData {
        $parts = explode('.', $name);
        $permission = Permission::firstOrCreate(['name' => $name], ['guard_name' => 'web', 'module_code' => strtoupper($parts[0]), 'resource' => $parts[1] ?? $parts[0], 'action' => end($parts)]);

        return new PermissionGrantData($permission->id, PermissionScope::School);
    }, $permissionNames);

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(userId: $user->id, schoolId: $f['school']->id, grants: $grants));

    return $user;
}

it('refuses every report card screen to a user without its permission', function (string $component): void {
    $f = rcFixture();
    $this->actingAs(rcUser($f, 'cbt.bank.manage'));

    Livewire::test($component, ['school' => $f['school']])->assertForbidden();
})->with([Review::class, Run::class, Withheld::class, Publish::class, Transcripts::class, Analytics::class]);

it('reviews a class, writes comments and approves its results', function (): void {
    $f = rcFixture();
    $this->actingAs(rcUser($f, 'academic.result.review', 'academic.result.comment'));
    $result = TermResult::where('student_id', $f['a']->id)->firstOrFail();

    Livewire::test(Review::class, ['school' => $f['school']])
        ->set('classId', $f['class']->id)->assertSee('Chipo')
        ->call('edit', $result->id)->set('classTeacherComment', 'Excellent term')->set('headComment', 'Keep going')->call('saveComments')->assertHasNoErrors()
        ->call('approve');

    expect($result->fresh()->class_teacher_comment)->toBe('Excellent term')->and($result->fresh()->status)->toBe('approved');
});

it('writes a per-subject comment onto term_subject_results alongside the term-level pair (ACA-05 gap)', function (): void {
    $f = rcFixture();
    $this->actingAs(rcUser($f, 'academic.result.review', 'academic.result.comment'));
    $result = TermResult::where('student_id', $f['a']->id)->firstOrFail();
    $subjectResult = TermSubjectResult::where('student_id', $f['a']->id)->where('term_id', $f['term']->id)->firstOrFail();

    Livewire::test(Review::class, ['school' => $f['school']])
        ->set('classId', $f['class']->id)
        ->call('edit', $result->id)
        ->set("subjectComments.{$subjectResult->id}", 'Strong grasp of the material this term.')
        ->call('saveComments')
        ->assertHasNoErrors();

    expect($subjectResult->fresh()->teacher_comment)->toBe('Strong grasp of the material this term.');
});

it('refuses to edit a subject comment once the term result is published', function (): void {
    $f = rcFixture();
    rcGenerate($f);
    app(PublishReportCardsAction::class)->execute(new PublishReportCardsData($f['school']->id, $f['term']->id, $f['user']->id, classId: $f['class']->id));
    $subjectResult = TermSubjectResult::where('student_id', $f['a']->id)->where('term_id', $f['term']->id)->firstOrFail();

    expect(fn () => app(SetTermSubjectResultCommentAction::class)->execute(
        new SetTermSubjectResultCommentData($subjectResult->id, 'Too late.'),
    ))->toThrow(InvalidStateTransitionException::class);
});

it('will not let a reviewer without the comment permission write comments', function (): void {
    $f = rcFixture();
    $this->actingAs(rcUser($f, 'academic.result.review'));

    Livewire::test(Review::class, ['school' => $f['school']])->set('classId', $f['class']->id)->call('edit', TermResult::value('id'))->assertForbidden();
});

it('generates then publishes report cards for a class from the screens', function (): void {
    $f = rcFixture();
    $this->actingAs(rcUser($f, 'academic.report_card.generate', 'academic.report_card.publish'));

    Livewire::test(Run::class, ['school' => $f['school']])->call('generate')->assertHasErrors('classId')
        ->set('classId', $f['class']->id)->call('generate')->assertHasNoErrors();
    expect(ReportCardRun::first()->generated_count)->toBe(2);

    Livewire::test(Publish::class, ['school' => $f['school']])->set('classId', $f['class']->id)->call('publish')->assertHasNoErrors();
    expect(TermResult::where('status', 'published')->count())->toBe(2);
});

it('lists withheld learners and only lets someone with the override permission release one', function (): void {
    $f = rcFixture();
    rcWithholdForFees($f, $f['b']);
    rcGenerate($f);
    $result = TermResult::where('student_id', $f['b']->id)->firstOrFail();

    $this->actingAs(rcUser($f, 'academic.report_card.view'));
    Livewire::test(Withheld::class, ['school' => $f['school']])->assertSee('Farai')->call('startOverride', $result->id)->assertForbidden();

    $this->actingAs(rcUser($f, 'academic.report_card.view', 'finance.report_gate.override'));
    Livewire::test(Withheld::class, ['school' => $f['school']])
        ->call('startOverride', $result->id)->call('override')->assertHasErrors('reason')
        ->set('reason', 'Hardship approved by the bursar.')->call('override')->assertHasNoErrors();

    expect(ReportGateOverride::where('student_id', $f['b']->id)->exists())->toBeTrue();
});

it('generates a transcript for a learner picked by search, and shows analytics', function (): void {
    $f = rcFixture();
    rcGenerate($f);
    app(PublishReportCardsAction::class)->execute(new PublishReportCardsData($f['school']->id, $f['term']->id, $f['user']->id, classId: $f['class']->id));

    $this->actingAs(rcUser($f, 'academic.transcript.generate', 'academic.result.view'));
    Livewire::test(Transcripts::class, ['school' => $f['school']])
        ->set('search', 'Chip')->assertSee('Chipo')->call('select', $f['a']->id)->call('generate')->assertHasNoErrors();
    expect(Document::where('document_type', 'transcript')->count())->toBe(1);

    Livewire::test(Analytics::class, ['school' => $f['school']])->assertSee('65');
});
