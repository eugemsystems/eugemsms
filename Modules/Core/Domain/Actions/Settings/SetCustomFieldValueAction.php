<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Settings;

use Illuminate\Support\Facades\Validator;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Settings\SetCustomFieldValueData;
use Modules\Core\Models\CustomFieldDefinition;
use Modules\Core\Models\CustomFieldValue;

/**
 * ACT-SetCustomFieldValue (Book A CORE-04 §4). BR-CORE-04-013: a required
 * custom field is enforced exactly as a core field would be — this
 * refuses a missing value with the field's own label in the message.
 * Stores into the one typed column matching `data_type`
 * (BR-CORE-04's "one column per data_type family" split), the rest left
 * null.
 */
final class SetCustomFieldValueAction extends Action
{
    public function execute(SetCustomFieldValueData $data): CustomFieldValue
    {
        $definition = CustomFieldDefinition::withoutGlobalScopes()->findOrFail($data->definitionId);

        $rules = ['required_if_missing' => $definition->is_required ? 'required' : 'nullable'];

        Validator::make(
            ['value' => $data->value],
            ['value' => array_filter([
                $rules['required_if_missing'],
                $definition->validation_rules,
            ])],
            [],
            ['value' => $definition->label],
        )->validate();

        $columns = [
            'value_text' => null,
            'value_number' => null,
            'value_date' => null,
            'value_bool' => null,
            'value_json' => null,
        ];

        $column = match ($definition->data_type) {
            'number', 'decimal', 'money' => 'value_number',
            'date', 'datetime' => 'value_date',
            'bool' => 'value_bool',
            'select', 'multiselect', 'file' => 'value_json',
            default => 'value_text',
        };

        $columns[$column] = $data->value;

        return $this->transaction(function () use ($definition, $data, $columns): CustomFieldValue {
            // Not CustomFieldValue::updateOrCreate(): that call's internal
            // lookup carries BelongsToSchool's global scope, filtered by
            // the *ambient* SchoolContext — this action can be called
            // without one set (e.g. from a queued import), and a
            // scope-filtered "no match" would create a duplicate row on
            // every call instead of updating the existing one.
            $existing = CustomFieldValue::withoutGlobalScopes()
                ->where('definition_id', $definition->id)
                ->where('entity_id', $data->entityId)
                ->first();

            $attributes = [
                'school_id' => $definition->school_id,
                'definition_id' => $definition->id,
                'entity_type' => $definition->entity_type,
                'entity_id' => $data->entityId,
                ...$columns,
            ];

            if ($existing !== null) {
                $existing->fill($attributes);
                $existing->save();

                return $existing;
            }

            return CustomFieldValue::create($attributes);
        });
    }
}
