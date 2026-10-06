<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Models\Guardian;
use Modules\People\Models\StudentGuardian;

/**
 * ACT-MergeGuardians (Book C PPL-03 BR-PPL-03-023, `guardians.merge`). Folds a duplicate guardian
 * record into the one that stays: every learner link, fee liability, sponsorship, booking, consent
 * and contact record that named the duplicate now names the survivor. The duplicate is kept, marked
 * `merged`, and pointed at the survivor — nothing is deleted, so history can still be traced.
 *
 * Refused when the two are different kinds of guardian, belong to different schools, either is
 * already merged, or each has its own parent-app account (withdraw one first — two sign-ins cannot
 * be silently joined). Where both were linked to the same learner, the survivor's link stays and
 * takes on every right either had (a court restriction on either wins), and the duplicate's link
 * is made inactive. Derived fee-default scores for the duplicate are dropped; they are recomputed.
 */
final class MergeGuardiansAction extends Action
{
    /**
     * Every plain `guardian id` column elsewhere, as table => columns.
     *
     * @var array<string, list<string>>
     */
    private const array REFERENCES = [
        'collection_attempts' => ['attempted_by_guardian_id'],
        'visitors' => ['linked_guardian_id'],
        'exeats' => ['requested_by_guardian_id', 'collecting_guardian_id', 'one_off_authorisation_by'],
        'visiting_day_bookings' => ['guardian_id'],
        'event_attendees' => ['guardian_id'],
        'consultation_bookings' => ['guardian_id'],
        'exit_interviews' => ['guardian_id'],
        'scholarship_applications' => ['applied_by_guardian_id'],
        'discount_awards' => ['sponsor_guardian_id'],
        'sponsorships' => ['guardian_id'],
        'households' => ['head_guardian_id'],
        'application_guardians' => ['existing_guardian_id'],
        'fee_liabilities' => ['guardian_id'],
        'guardian_contact_updates' => ['guardian_id'],
        'guardian_verification' => ['guardian_id'],
        'student_wallets' => ['controls_set_by'],
        'appeals' => ['lodged_by_guardian_id'],
        'medical_consents' => ['guardian_id'],
    ];

    public function execute(int $survivorId, int $duplicateId, int $mergedByUserId): Guardian
    {
        $survivor = Guardian::query()->findOrFail($survivorId);
        $duplicate = Guardian::query()->findOrFail($duplicateId);
        $errors = [];

        if ($survivor->id === $duplicate->id) {
            $errors['guardian'] = 'A guardian cannot be merged into themselves.';
        } elseif ($survivor->status === 'merged' || $duplicate->status === 'merged') {
            $errors['guardian'] = 'One of these guardians has already been merged.';
        } elseif ($survivor->guardian_type !== $duplicate->guardian_type) {
            $errors['guardian'] = 'A person and an organisation cannot be merged.';
        } elseif ($survivor->user_id !== null && $duplicate->user_id !== null && $survivor->user_id !== $duplicate->user_id) {
            $errors['guardian'] = 'Both guardians have their own parent-app account. Withdraw one account\'s access first.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $this->transaction(function () use ($survivor, $duplicate, $mergedByUserId): Guardian {
            $this->mergeLearnerLinks($survivor, $duplicate);

            foreach (self::REFERENCES as $table => $columns) {
                foreach ($columns as $column) {
                    DB::table($table)->where($column, $duplicate->id)->update([$column => $survivor->id]);
                }
            }

            DB::table('fee_default_risk_scores')->where('guardian_id', $duplicate->id)->delete();

            $userId = $survivor->user_id ?? $duplicate->user_id;

            $duplicate->forceFill(['user_id' => null, 'status' => 'merged', 'merged_into_id' => $survivor->id, 'merged_at' => Carbon::now(), 'merged_by' => $mergedByUserId])->save();
            $survivor->forceFill(['user_id' => $userId, 'updated_by' => $mergedByUserId])->save();

            return $survivor->refresh();
        });
    }

    private function mergeLearnerLinks(Guardian $survivor, Guardian $duplicate): void
    {
        $theirs = StudentGuardian::query()->where('guardian_id', $duplicate->id)->get();
        $ours = StudentGuardian::query()->where('guardian_id', $survivor->id)->get()->keyBy('student_id');

        foreach ($theirs as $link) {
            $kept = $ours->get($link->student_id);

            if ($kept === null) {
                $link->forceFill(['guardian_id' => $survivor->id])->save();

                continue;
            }

            $kept->forceFill([
                'is_primary_contact' => $kept->is_primary_contact || $link->is_primary_contact,
                'is_emergency_contact' => $kept->is_emergency_contact || $link->is_emergency_contact,
                'is_fee_responsible' => $kept->is_fee_responsible || $link->is_fee_responsible,
                'may_collect_learner' => $kept->may_collect_learner || $link->may_collect_learner,
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
}
