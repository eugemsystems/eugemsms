<?php

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\MergeDuplicateStudentsAction;
use Modules\People\Domain\DataObjects\MergeStudentsData;
use Modules\People\Livewire\Students\Duplicates;
use Modules\People\Models\FeeLiability;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;
use Modules\People\Models\StudentDocument;
use Modules\People\Models\StudentGuardian;
use Modules\People\Models\StudentMerge;
use Modules\People\Models\StudentPriorSchool;
use Modules\People\Models\StudentSibling;
use Modules\People\Models\StudentTimelineEvent;

/**
 * @return array{school: School, user: User}
 */
function learnerMergeFixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $user = User::factory()->create();
    $user->schools()->attach($school, ['status' => 'active']);

    return ['school' => $school, 'user' => $user];
}

/**
 * @param  array<string, mixed>  $f
 */
function learnerMergeUser(array $f, string ...$permissionNames): User
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

it('refuses to merge a learner into themselves or one already merged', function (): void {
    $f = learnerMergeFixture();
    $a = Student::factory()->for($f['school'])->create();
    $b = Student::factory()->for($f['school'])->create();
    $action = app(MergeDuplicateStudentsAction::class);

    expect(fn () => $action->execute(new MergeStudentsData($a->id, $a->id, $f['user']->id)))->toThrow(ValidationException::class);

    $action->execute(new MergeStudentsData($a->id, $b->id, $f['user']->id));

    expect(fn () => $action->execute(new MergeStudentsData($a->id, $b->id, $f['user']->id)))->toThrow(ValidationException::class);
});

it('merges guardian links, siblings, documents, prior schools and timeline onto the survivor, leaves financial records on the merged student, and writes a permanent merge record', function (): void {
    $f = learnerMergeFixture();
    $survivor = Student::factory()->for($f['school'])->create();
    $duplicate = Student::factory()->for($f['school'])->create();
    $stranger = Student::factory()->for($f['school'])->create();

    $sharedGuardian = Guardian::factory()->for($f['school'])->create();
    $onlyDuplicateGuardian = Guardian::factory()->for($f['school'])->create();
    StudentGuardian::factory()->create(['school_id' => $f['school']->id, 'student_id' => $survivor->id, 'guardian_id' => $sharedGuardian->id, 'may_collect_learner' => false, 'has_court_restriction' => false]);
    StudentGuardian::factory()->create(['school_id' => $f['school']->id, 'student_id' => $duplicate->id, 'guardian_id' => $sharedGuardian->id, 'may_collect_learner' => true, 'has_court_restriction' => true]);
    StudentGuardian::factory()->create(['school_id' => $f['school']->id, 'student_id' => $duplicate->id, 'guardian_id' => $onlyDuplicateGuardian->id]);

    StudentSibling::create(['school_id' => $f['school']->id, 'student_id' => $duplicate->id, 'sibling_student_id' => $stranger->id, 'relationship' => 'sister', 'created_at' => now()]);
    StudentSibling::create(['school_id' => $f['school']->id, 'student_id' => $stranger->id, 'sibling_student_id' => $duplicate->id, 'relationship' => 'brother', 'created_at' => now()]);
    // Duplicate already linked to the survivor as a sibling -- merging must drop this rather than self-reference.
    StudentSibling::create(['school_id' => $f['school']->id, 'student_id' => $duplicate->id, 'sibling_student_id' => $survivor->id, 'relationship' => 'sister', 'created_at' => now()]);

    $document = StudentDocument::factory()->create(['school_id' => $f['school']->id, 'student_id' => $duplicate->id]);
    $priorSchool = StudentPriorSchool::factory()->create(['school_id' => $f['school']->id, 'student_id' => $duplicate->id]);
    $timelineEvent = StudentTimelineEvent::factory()->create(['school_id' => $f['school']->id, 'student_id' => $duplicate->id]);
    $liability = FeeLiability::factory()->create(['school_id' => $f['school']->id, 'student_id' => $duplicate->id, 'guardian_id' => $sharedGuardian->id]);

    $survived = app(MergeDuplicateStudentsAction::class)->execute(new MergeStudentsData(
        survivingStudentId: $survivor->id, mergedStudentId: $duplicate->id, mergedByUserId: $f['user']->id, reason: 'Confirmed same learner',
    ));

    expect($survived->id)->toBe($survivor->id);
    $duplicate->refresh();
    expect($duplicate->status)->toBe('merged')
        ->and($duplicate->merged_into_id)->toBe($survivor->id)
        ->and($duplicate->merged_by)->toBe($f['user']->id)
        ->and($duplicate->merged_at)->not->toBeNull()
        // admission_number is immutable and never touched by a merge (BR-PPL-01-001/010).
        ->and($duplicate->admission_number)->not->toBeNull();

    $record = StudentMerge::sole();
    expect($record->surviving_student_id)->toBe($survivor->id)
        ->and($record->merged_student_id)->toBe($duplicate->id)
        ->and($record->merged_student_admission_number)->toBe($duplicate->admission_number)
        ->and($record->reason)->toBe('Confirmed same learner');

    $kept = StudentGuardian::where('student_id', $survivor->id)->where('guardian_id', $sharedGuardian->id)->firstOrFail();
    expect($kept->may_collect_learner)->toBeTrue()->and($kept->has_court_restriction)->toBeTrue()
        ->and(StudentGuardian::where('student_id', $duplicate->id)->where('guardian_id', $sharedGuardian->id)->value('status'))->toBe('inactive')
        ->and(StudentGuardian::where('student_id', $survivor->id)->where('guardian_id', $onlyDuplicateGuardian->id)->exists())->toBeTrue();

    expect(StudentSibling::where('student_id', $survivor->id)->where('sibling_student_id', $stranger->id)->exists())->toBeTrue()
        ->and(StudentSibling::where('student_id', $stranger->id)->where('sibling_student_id', $survivor->id)->exists())->toBeTrue()
        ->and(StudentSibling::where('sibling_student_id', $survivor->id)->where('student_id', $survivor->id)->exists())->toBeFalse()
        ->and(StudentSibling::where('student_id', $duplicate->id)->exists())->toBeFalse();

    expect($document->fresh()->student_id)->toBe($survivor->id)
        ->and($priorSchool->fresh()->student_id)->toBe($survivor->id)
        ->and($timelineEvent->fresh()->student_id)->toBe($survivor->id)
        ->and(StudentTimelineEvent::where('student_id', $survivor->id)->where('event_type', 'learner_merged')->exists())->toBeTrue();

    // The one thing that must NEVER move: a financial record stays on the merged-away student.
    expect($liability->fresh()->student_id)->toBe($duplicate->id);
});

it('cannot be undone -- a merge record can never be updated or deleted', function (): void {
    $f = learnerMergeFixture();
    $record = StudentMerge::factory()->for($f['school'])->create();

    expect(fn () => $record->update(['reason' => 'changed my mind']))->toThrow(InvalidStateTransitionException::class)
        ->and(fn () => $record->delete())->toThrow(InvalidStateTransitionException::class);
});

it('refuses the merge screen without people.students.merge, and lets a holder merge two scanned results', function (): void {
    $f = learnerMergeFixture();
    $survivor = Student::factory()->for($f['school'])->create(['first_name' => 'Rudo', 'last_name' => 'Chikosi', 'date_of_birth' => '2014-06-01']);
    $duplicate = Student::factory()->for($f['school'])->create(['first_name' => 'Rudo', 'last_name' => 'Chikosi', 'date_of_birth' => '2014-06-01']);

    $this->actingAs(learnerMergeUser($f, 'cbt.bank.manage'));
    Livewire::test(Duplicates::class, ['school' => $f['school']])->assertForbidden();

    $this->actingAs(learnerMergeUser($f, 'people.students.merge'));
    Livewire::test(Duplicates::class, ['school' => $f['school']])
        ->set('firstName', 'Rudo')->set('lastName', 'Chikosi')->set('dateOfBirth', '2014-06-01')
        ->call('scan')
        ->assertSee('2 possible match')
        ->call('merge', $survivor->id, $duplicate->id)
        ->assertHasNoErrors();

    expect($duplicate->fresh()->status)->toBe('merged')
        ->and($duplicate->fresh()->merged_into_id)->toBe($survivor->id);
});
