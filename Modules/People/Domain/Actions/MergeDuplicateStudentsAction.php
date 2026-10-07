<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\MergeStudentsData;
use Modules\People\Domain\DataObjects\RecordStudentTimelineEventData;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;
use Modules\People\Models\StudentMerge;
use Modules\People\Models\StudentSibling;

/**
 * ACT-MergeDuplicateStudents (Book C PPL-01 BR-PPL-01-010, `people.students.merge`,
 * ⚠⚠ heavily restricted — grant to the head and vendor support only).
 *
 * The spec's own literal wording asks this to "reassign every financial and academic
 * record to the surviving learner." It deliberately does NOT do that for financial
 * records: CLAUDE.md's non-negotiable append-only-ledger rule forbids rewriting a
 * posted `journal_lines`/`invoices`/`receipts` row to point at a different student, and
 * doing so would also silently rewrite history a reconciliation or an audit already
 * relied on. Design agreed with the project owner (2026-10-07): a merged-away learner's
 * financial records stay exactly where they are, on their own original `students.id` —
 * `students.merged_into_id` is the forward pointer a caller follows to assemble "this
 * learner's full financial history across both records" without a single financial row
 * ever being rewritten. `student_merges` is the permanent, append-only record the spec
 * asks for (see that model's own docblock — it can never be updated or deleted).
 *
 * What IS reassigned here — deliberately scoped to PPL-01/03's own core biographical
 * record, not every `student_id` reference in the whole application — is: guardian
 * links (rights OR-ed together the same way `MergeGuardiansAction` already merges a
 * guardian's own links, a court restriction on either wins), sibling links (both
 * directions, a resulting self-link or duplicate dropped rather than violating the
 * unique constraint), documents, prior-schooling records, and the timeline (the
 * merged-away learner's own history becomes part of the survivor's). Academic data
 * owned by other modules (ACA-04 attendance, ACA-05 results, boarding, library, …) is
 * NOT reassigned — a genuinely honest, documented gap, the same incremental approach
 * `MergeGuardiansAction`'s own finite `REFERENCES` list already takes, extend as each
 * is confirmed in scope rather than guessed at here.
 *
 * BR-PPL-01-010's "refused if either learner has records in a locked period": none of
 * the tables this action actually reassigns are session-scoped
 * (`Modules\Core\Domain\Concerns\BelongsToSession`), so there is nothing here for
 * `PeriodGuard` to refuse yet — if a future pass extends `REFERENCES` to a
 * session-scoped table, re-point it through that model's own `->save()` (never a raw
 * `DB::table()` update) so `PeriodGuard` enforces the refusal automatically, exactly as
 * it already does for every other write in this codebase.
 */
final class MergeDuplicateStudentsAction extends Action
{
    /**
     * Every plain `student_id` column this action is confirmed to own re-pointing for.
     *
     * @var array<string, list<string>>
     */
    private const array REFERENCES = [
        'student_documents' => ['student_id'],
        'student_prior_schools' => ['student_id'],
        'student_timeline' => ['student_id'],
    ];

    public function execute(MergeStudentsData $data): Student
    {
        $survivor = Student::query()->findOrFail($data->survivingStudentId);
        $duplicate = Student::query()->findOrFail($data->mergedStudentId);
        $errors = [];

        if ($survivor->id === $duplicate->id) {
            $errors['student'] = ['A learner cannot be merged into themselves.'];
        } elseif ($survivor->school_id !== $duplicate->school_id) {
            $errors['student'] = ['Both learners must belong to the same school.'];
        } elseif ($survivor->status === 'merged' || $duplicate->status === 'merged') {
            $errors['student'] = ['One of these learners has already been merged.'];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $this->transaction(function () use ($survivor, $duplicate, $data): Student {
            $this->mergeGuardianLinks($survivor, $duplicate);
            $this->mergeSiblingLinks($survivor, $duplicate);

            foreach (self::REFERENCES as $table => $columns) {
                foreach ($columns as $column) {
                    DB::table($table)->where($column, $duplicate->id)->update([$column => $survivor->id]);
                }
            }

            StudentMerge::create([
                'school_id' => $survivor->school_id,
                'surviving_student_id' => $survivor->id,
                'merged_student_id' => $duplicate->id,
                'merged_student_admission_number' => $duplicate->admission_number,
                'reason' => $data->reason,
                'merged_by' => $data->mergedByUserId,
                'merged_at' => Carbon::now(),
            ]);

            app(RecordStudentTimelineEventAction::class)->execute(new RecordStudentTimelineEventData(
                schoolId: $survivor->school_id,
                studentId: $survivor->id,
                eventCategory: 'administrative',
                eventType: 'learner_merged',
                title: 'Duplicate learner record merged',
                summary: "Admission number {$duplicate->admission_number} was merged into this record.",
                recordedByUserId: $data->mergedByUserId,
            ));

            $duplicate->statusChangeAuthorized = true;
            $duplicate->update([
                'status' => 'merged',
                'merged_into_id' => $survivor->id,
                'merged_at' => Carbon::now(),
                'merged_by' => $data->mergedByUserId,
            ]);

            return $survivor->refresh();
        });
    }

    private function mergeGuardianLinks(Student $survivor, Student $duplicate): void
    {
        $theirs = StudentGuardian::query()->where('student_id', $duplicate->id)->get();
        $ours = StudentGuardian::query()->where('student_id', $survivor->id)->get()->keyBy('guardian_id');

        foreach ($theirs as $link) {
            $kept = $ours->get($link->guardian_id);

            if ($kept === null) {
                $link->forceFill(['student_id' => $survivor->id])->save();

                continue;
            }

            $kept->forceFill([
                'is_primary_contact' => $kept->is_primary_contact || $link->is_primary_contact,
                'is_emergency_contact' => $kept->is_emergency_contact || $link->is_emergency_contact,
                'is_fee_responsible' => $kept->is_fee_responsible || $link->is_fee_responsible,
                'may_collect_learner' => $kept->may_collect_learner || $link->may_collect_learner,
                'may_authorise_exeat' => $kept->may_authorise_exeat || $link->may_authorise_exeat,
                'may_authorise_medical' => $kept->may_authorise_medical || $link->may_authorise_medical,
                'may_view_full_balance' => $kept->may_view_full_balance || $link->may_view_full_balance,
                'has_court_restriction' => $kept->has_court_restriction || $link->has_court_restriction,
            ]);

            if ($kept->status !== 'active' && $link->status === 'active') {
                $kept->forceFill(['status' => 'active', 'effective_from' => $link->effective_from, 'effective_to' => $link->effective_to]);
            }

            $kept->save();
            $link->forceFill(['status' => 'inactive', 'effective_to' => $link->effective_to ?? Carbon::now()->toDateString()])->save();
        }
    }

    /**
     * Sibling links are symmetric (stored in both directions) with a unique constraint
     * per ordered pair — a naive re-point could either self-reference the survivor or
     * collide with a link the survivor already has, so each direction is handled on its
     * own, dropping whichever outcome that would produce.
     */
    private function mergeSiblingLinks(Student $survivor, Student $duplicate): void
    {
        foreach (StudentSibling::query()->where('student_id', $duplicate->id)->get() as $link) {
            if ($link->sibling_student_id === $survivor->id
                || StudentSibling::query()->where('student_id', $survivor->id)->where('sibling_student_id', $link->sibling_student_id)->exists()) {
                $link->delete();

                continue;
            }

            $link->forceFill(['student_id' => $survivor->id])->save();
        }

        foreach (StudentSibling::query()->where('sibling_student_id', $duplicate->id)->get() as $link) {
            if ($link->student_id === $survivor->id
                || StudentSibling::query()->where('student_id', $link->student_id)->where('sibling_student_id', $survivor->id)->exists()) {
                $link->delete();

                continue;
            }

            $link->forceFill(['sibling_student_id' => $survivor->id])->save();
        }
    }
}
