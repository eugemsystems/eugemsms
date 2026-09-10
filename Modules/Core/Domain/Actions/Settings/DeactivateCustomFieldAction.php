<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Settings;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Settings\DeactivateCustomFieldData;
use Modules\Core\Domain\Events\Settings\CustomFieldDeactivated;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Models\CustomFieldDefinition;
use Modules\Core\Models\CustomFieldValue;

/**
 * ACT-DeactivateCustomField (Book A CORE-04 §4). BR-CORE-04-010:
 * deactivating hides the field from forms but preserves every stored
 * value; hard deletion is only permitted when zero values exist.
 */
final class DeactivateCustomFieldAction extends Action
{
    public function execute(DeactivateCustomFieldData $data): void
    {
        $definition = CustomFieldDefinition::withoutGlobalScopes()->findOrFail($data->definitionId);

        $this->transaction(function () use ($definition, $data): void {
            $definition->is_active = false;
            $definition->updated_by = $data->actingUserId;
            $definition->save();

            event(new CustomFieldDeactivated($definition));

            if (! $data->deleteIfUnused) {
                return;
            }

            if (CustomFieldValue::withoutGlobalScopes()->where('definition_id', $definition->id)->exists()) {
                throw new InvalidStateTransitionException(
                    'This custom field has recorded values and cannot be deleted — only deactivated.',
                    ['definition_id' => $definition->id],
                );
            }

            $definition->delete();
        });
    }
}
