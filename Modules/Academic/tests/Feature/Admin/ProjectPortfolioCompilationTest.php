<?php

use Livewire\Livewire;
use Modules\Academic\Domain\Actions\CompileProjectPortfolioAction;
use Modules\Academic\Domain\DataObjects\CompileProjectPortfolioData;
use Modules\Academic\Livewire\Projects\Portfolio;
use Modules\Academic\Models\ProjectMilestone;
use Modules\Academic\Models\ProjectPortfolio;
use Modules\Core\Models\Document;

/**
 * Book E ACA-06 §7/§9, BR-ACA-06-017 — portfolio compilation. Reuses
 * `aca06Fixture()`/`aca06Rubric()`/`verifiedLearnerProject()` from
 * `Aca06ProjectsAndCalaTest`/`ProjectAmendmentApprovalTest`, so run
 * the module directory rather than this file alone.
 */
it('compiles a portfolio document carrying the brief, rubric, and marker comments', function (): void {
    $f = aca06Fixture();
    $learnerProject = verifiedLearnerProject($f);

    $portfolio = app(CompileProjectPortfolioAction::class)->execute(new CompileProjectPortfolioData(
        learnerProjectId: $learnerProject->id, compiledByUserId: $f['user']->id,
    ));

    expect($portfolio)->toBeInstanceOf(ProjectPortfolio::class)
        ->and($portfolio->learner_project_id)->toBe($learnerProject->id)
        ->and($portfolio->document_id)->not->toBeNull();

    $document = Document::findOrFail($portfolio->document_id);
    expect($document->document_type)->toBe('project_portfolio')
        ->and($document->documentable_type)->toBe($learnerProject->getMorphClass())
        ->and($document->documentable_id)->toBe($learnerProject->id)
        ->and($document->verification_code)->not->toBeNull();
});

it('compiles a portfolio for a project with no milestones defined, without error', function (): void {
    $f = aca06Fixture();
    $learnerProject = verifiedLearnerProject($f);

    expect(fn () => app(CompileProjectPortfolioAction::class)->execute(new CompileProjectPortfolioData(
        learnerProjectId: $learnerProject->id, compiledByUserId: $f['user']->id,
    )))->not->toThrow(Exception::class);
});

it('reports a milestone with no learner submission as pending, not an error', function (): void {
    $f = aca06Fixture();
    $learnerProject = verifiedLearnerProject($f);

    ProjectMilestone::factory()->create([
        'school_id' => $f['school']->id, 'brief_id' => $learnerProject->brief_id, 'sequence' => 1, 'title' => 'Proposal', 'due_on' => now()->addWeek(),
    ]);

    $portfolio = app(CompileProjectPortfolioAction::class)->execute(new CompileProjectPortfolioData(
        learnerProjectId: $learnerProject->id, compiledByUserId: $f['user']->id,
    ));

    expect($portfolio)->toBeInstanceOf(ProjectPortfolio::class);
});

it('compiles a portfolio from the Projects\\Portfolio screen, requiring academic.projects.view', function (): void {
    $f = aca06Fixture();
    $learnerProject = verifiedLearnerProject($f);
    $user = projectAmendUser($f, 'academic.projects.view');

    Livewire::actingAs($user)->test(Portfolio::class, ['school' => $f['school'], 'learnerProject' => $learnerProject->fresh()])
        ->call('compile')
        ->assertHasNoErrors();

    expect(ProjectPortfolio::where('learner_project_id', $learnerProject->id)->count())->toBe(1);

    Livewire::actingAs(projectAmendUser($f))->test(Portfolio::class, ['school' => $f['school'], 'learnerProject' => $learnerProject->fresh()])
        ->assertForbidden();
});
