<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Auth;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Auth\LinkAccountData;
use Modules\Core\Models\UserAccountLink;

/**
 * ACT-LinkAccount (Book A CORE-05 §3). Parent↔learner, staff↔teacher
 * identity linking — `linked_type`/`linked_id` point at records owned
 * by modules that don't exist yet. Looked up via `withoutGlobalScopes()`
 * rather than `updateOrCreate()` — `UserAccountLink` uses
 * `BelongsToSchool`, whose scope would otherwise filter the existence
 * check to the ambient `SchoolContext` rather than the school given on
 * the DTO (see the project rule on this recurring trap in
 * `.ai/rules/core.md`).
 */
final class LinkAccountAction extends Action
{
    public function execute(LinkAccountData $data): UserAccountLink
    {
        return $this->transaction(function () use ($data): UserAccountLink {
            $link = UserAccountLink::withoutGlobalScopes()
                ->where('school_id', $data->schoolId)
                ->where('user_id', $data->userId)
                ->where('linked_type', $data->linkedType)
                ->where('linked_id', $data->linkedId)
                ->first();

            if ($link === null) {
                $link = UserAccountLink::create([
                    'school_id' => $data->schoolId,
                    'user_id' => $data->userId,
                    'linked_type' => $data->linkedType,
                    'linked_id' => $data->linkedId,
                    'is_active' => true,
                ]);
            } elseif (! $link->is_active) {
                $link->forceFill(['is_active' => true])->save();
            }

            return $link;
        });
    }
}
