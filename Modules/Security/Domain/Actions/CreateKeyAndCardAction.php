<?php

declare(strict_types=1);

namespace Modules\Security\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Security\Domain\DataObjects\CreateKeyAndCardData;
use Modules\Security\Models\KeyAndCard;

/**
 * ACT-CreateKeyAndCard (Book H2 OPS-06 §2/BR-OPS-06-006). A new,
 * gap-filling Action for this pass — the spec's own data model and
 * screen table describe a `keys_and_cards` register and an `IssueKeyAction`/
 * `ReturnKeyAction` pair that issue and return an existing key, but no
 * Action anywhere in the shipped domain layer ever created the key
 * record itself (verified by grep across `Modules\Security\Domain\Actions`).
 * Always created `available` — `IssueKeyAction` is the only path that
 * moves it to `issued`, and that action's own master-key gate
 * (`MasterKeyRequiresAuthorityException`) applies from the first issue
 * onward regardless of how the record was created.
 */
final class CreateKeyAndCardAction extends Action
{
    public function execute(CreateKeyAndCardData $data): KeyAndCard
    {
        return $this->transaction(fn (): KeyAndCard => KeyAndCard::create([
            'school_id' => $data->schoolId,
            'identifier' => $data->identifier,
            'item_type' => $data->itemType,
            'description' => $data->description,
            'opens_location' => $data->opensLocation,
            'is_master' => $data->isMaster,
            'status' => 'available',
        ]));
    }
}
