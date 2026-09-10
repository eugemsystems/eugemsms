<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Settings;

use Illuminate\Support\Facades\Validator;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Settings\DefineCustomFieldData;
use Modules\Core\Domain\Events\Settings\CustomFieldDefined;
use Modules\Core\Models\CustomFieldDefinition;

/**
 * ACT-DefineCustomField (Book A CORE-04 §4). BR-CORE-04-009: `key` unique
 * per (school, entity_type) and immutable from here on — enforced by the
 * model itself, not just this action. BR-CORE-04-011: `is_searchable`
 * provisioning a generated column via a queued migration belongs to
 * CORE-04's job runner, not built yet — the flag is stored, but no
 * column is provisioned until that job exists; searches on this field
 * simply aren't available until then, exactly as the rule anticipates
 * ("unavailable for search until that completes").
 */
final class DefineCustomFieldAction extends Action
{
    public function execute(DefineCustomFieldData $data): CustomFieldDefinition
    {
        Validator::make(
            [
                'entity_type' => $data->entityType,
                'key' => $data->key,
                'label' => $data->label,
                'data_type' => $data->dataType,
            ],
            [
                'entity_type' => ['required', 'string', 'max:60'],
                'key' => [
                    'required', 'string', 'max:60', 'regex:/^[a-z][a-z0-9_]*$/',
                    'unique:custom_field_definitions,key,NULL,id,school_id,'.$data->schoolId.',entity_type,'.$data->entityType,
                ],
                'label' => ['required', 'string', 'max:150'],
                'data_type' => ['required', 'in:text,textarea,number,decimal,date,datetime,bool,select,multiselect,file,money,email,phone'],
            ],
        )->validate();

        return $this->transaction(function () use ($data): CustomFieldDefinition {
            $definition = CustomFieldDefinition::create([
                'school_id' => $data->schoolId,
                'entity_type' => $data->entityType,
                'key' => $data->key,
                'label' => $data->label,
                'description' => $data->description,
                'data_type' => $data->dataType,
                'options' => $data->options,
                'validation_rules' => $data->validationRules,
                'is_required' => $data->isRequired,
                'is_searchable' => $data->isSearchable,
                'is_exposed_in_api' => $data->isExposedInApi,
                'is_printable' => $data->isPrintable,
                'visible_to_roles' => $data->visibleToRoles,
                'group_label' => $data->groupLabel,
                'sort_order' => $data->sortOrder,
                'is_active' => true,
                'created_by' => $data->actingUserId,
                'updated_by' => $data->actingUserId,
            ]);

            event(new CustomFieldDefined($definition));

            return $definition;
        });
    }
}
