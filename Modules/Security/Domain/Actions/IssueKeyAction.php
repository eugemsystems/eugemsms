<?php

declare(strict_types=1);

namespace Modules\Security\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Security\Domain\DataObjects\IssueKeyData;
use Modules\Security\Domain\Exceptions\MasterKeyRequiresAuthorityException;
use Modules\Security\Models\KeyAndCard;
use Modules\Security\Models\KeyIssue;

/**
 * ACT-IssueKey (Book H2 OPS-06 §4/BR-OPS-06-006). A master key is
 * refused outright without an explicit higher-authority confirmation
 * on the request — `CORE-07`'s real multi-step chain isn't wired into
 * any domain module yet anywhere in this codebase, the same
 * single-gate boundary every other module's own approval-adjacent
 * action already uses.
 */
final class IssueKeyAction extends Action
{
    public function execute(IssueKeyData $data): KeyIssue
    {
        $key = KeyAndCard::findOrFail($data->keyId);

        if ($key->status !== 'available') {
            throw ValidationException::withMessages([
                'keyId' => "Key/card #{$key->id} is not available to issue (currently {$key->status}).",
            ]);
        }

        if ($key->is_master && ! $data->higherAuthorityConfirmed) {
            throw MasterKeyRequiresAuthorityException::forKey($key->id);
        }

        if ($data->issuedToStaffId === null && $data->issuedToContractorId === null) {
            throw ValidationException::withMessages([
                'issuedToStaffId' => 'A key must be issued to either a staff member or a contractor.',
            ]);
        }

        return $this->transaction(function () use ($key, $data): KeyIssue {
            $issue = KeyIssue::create([
                'school_id' => $key->school_id,
                'key_id' => $key->id,
                'issued_to_staff_id' => $data->issuedToStaffId,
                'issued_to_contractor_id' => $data->issuedToContractorId,
                'issued_at' => Carbon::now(),
                'issued_by' => $data->issuedByUserId,
                'due_back_on' => $data->dueBackOn?->toDateString(),
                'status' => 'issued',
            ]);

            $key->update(['status' => 'issued']);

            return $issue;
        });
    }
}
