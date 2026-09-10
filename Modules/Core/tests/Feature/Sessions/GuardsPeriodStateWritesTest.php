<?php

use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Term;

it('refuses a direct write to a term\'s academic_state (BR-CORE-03-008)', function (): void {
    $term = Term::factory()->create(['academic_state' => 'planned']);

    $term->academic_state = 'open';
    $term->save();
})->throws(InvalidStateTransitionException::class);

it('refuses a direct write to a term\'s financial_state', function (): void {
    $term = Term::factory()->create(['financial_state' => 'planned']);

    $term->financial_state = 'open';
    $term->save();
})->throws(InvalidStateTransitionException::class);

it('refuses a direct write to an academic year\'s state', function (): void {
    $year = AcademicYear::factory()->create(['academic_state' => 'planned']);

    $year->academic_state = 'open';
    $year->save();
})->throws(InvalidStateTransitionException::class);

it('allows the write once stateTransitionAuthorized is set (the gateway\'s own escape hatch)', function (): void {
    $term = Term::factory()->create(['academic_state' => 'planned']);

    $term->stateTransitionAuthorized = true;
    $term->academic_state = 'open';
    $term->save();

    expect($term->fresh()->academic_state->value)->toBe('open');
});

it('allows setting the initial state freely at creation', function (): void {
    $term = Term::factory()->create(['academic_state' => 'planned', 'financial_state' => 'open']);

    expect($term->academic_state->value)->toBe('planned')
        ->and($term->financial_state->value)->toBe('open');
});
