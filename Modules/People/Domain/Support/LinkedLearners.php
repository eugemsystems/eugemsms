<?php

declare(strict_types=1);

namespace Modules\People\Domain\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Modules\People\Models\Guardian;
use Modules\People\Models\StudentGuardian;

/**
 * The learners a signed-in guardian may see through the API: the active, currently effective
 * links of the guardian record that belongs to the user, in the current school. This is the
 * one place a parent-facing endpoint decides "whose child is this" — never a client-supplied
 * id on its own. The tiered flag `may_view_full_balance` lives on the link, so a field gated
 * by it is left out of the response, not merely hidden by the client.
 */
final class LinkedLearners
{
    /**
     * @return Collection<int, StudentGuardian>
     */
    public function forUser(User $user): Collection
    {
        $guardianIds = Guardian::query()->where('user_id', $user->id)->pluck('id');

        return StudentGuardian::query()
            ->whereIn('guardian_id', $guardianIds)
            ->where('status', 'active')
            ->whereDate('effective_from', '<=', now()->toDateString())
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', now()->toDateString()))
            ->with('student')
            ->get()
            ->filter(fn (StudentGuardian $link): bool => $link->student !== null)
            ->values();
    }

    public function linkFor(User $user, string $studentUlid): ?StudentGuardian
    {
        return $this->forUser($user)->first(fn (StudentGuardian $link): bool => $link->student->ulid === $studentUlid);
    }
}
