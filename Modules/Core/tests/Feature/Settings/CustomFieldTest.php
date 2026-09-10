<?php

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Settings\DeactivateCustomFieldAction;
use Modules\Core\Domain\Actions\Settings\DefineCustomFieldAction;
use Modules\Core\Domain\Actions\Settings\SetCustomFieldValueAction;
use Modules\Core\Domain\DataObjects\Settings\DeactivateCustomFieldData;
use Modules\Core\Domain\DataObjects\Settings\DefineCustomFieldData;
use Modules\Core\Domain\DataObjects\Settings\SetCustomFieldValueData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Models\CustomFieldDefinition;
use Modules\Core\Models\CustomFieldValue;
use Modules\Core\Models\School;

it('defines a custom field', function (): void {
    $school = School::factory()->create();

    $definition = app(DefineCustomFieldAction::class)->execute(new DefineCustomFieldData(
        schoolId: $school->id,
        entityType: 'student',
        key: 'parish',
        label: 'Parish',
        dataType: 'text',
        isRequired: true,
    ));

    expect($definition->exists)->toBeTrue()->and($definition->is_active)->toBeTrue();
});

it('rejects a duplicate key for the same entity type on the same school (BR-CORE-04-009)', function (): void {
    $school = School::factory()->create();

    app(DefineCustomFieldAction::class)->execute(new DefineCustomFieldData($school->id, 'student', 'parish', 'Parish', 'text'));
    app(DefineCustomFieldAction::class)->execute(new DefineCustomFieldData($school->id, 'student', 'parish', 'Parish Again', 'text'));
})->throws(ValidationException::class);

it('the key is immutable after creation', function (): void {
    $definition = CustomFieldDefinition::factory()->create(['key' => 'parish']);

    $definition->key = 'something_else';
    $definition->save();
})->throws(InvalidStateTransitionException::class);

it('deactivating hides the field but preserves values (BR-CORE-04-010)', function (): void {
    $definition = CustomFieldDefinition::factory()->create();
    CustomFieldValue::factory()->for($definition, 'definition')->create(['school_id' => $definition->school_id]);

    app(DeactivateCustomFieldAction::class)->execute(new DeactivateCustomFieldData($definition->id));

    expect($definition->fresh()->is_active)->toBeFalse()
        ->and(CustomFieldValue::withoutGlobalScopes()->where('definition_id', $definition->id)->count())->toBe(1);
});

it('refuses to delete a field that has recorded values', function (): void {
    $definition = CustomFieldDefinition::factory()->create();
    CustomFieldValue::factory()->for($definition, 'definition')->create(['school_id' => $definition->school_id]);

    app(DeactivateCustomFieldAction::class)->execute(new DeactivateCustomFieldData($definition->id, deleteIfUnused: true));
})->throws(InvalidStateTransitionException::class);

it('deletes a field with zero recorded values when asked', function (): void {
    $definition = CustomFieldDefinition::factory()->create();

    app(DeactivateCustomFieldAction::class)->execute(new DeactivateCustomFieldData($definition->id, deleteIfUnused: true));

    expect(CustomFieldDefinition::find($definition->id))->toBeNull();
});

it('requires a value for a required field (AC-CORE-04-003)', function (): void {
    $definition = CustomFieldDefinition::factory()->create(['is_required' => true, 'label' => 'Parish']);

    app(SetCustomFieldValueAction::class)->execute(new SetCustomFieldValueData($definition->id, 1, null));
})->throws(ValidationException::class);

it('stores a value in the column matching its data type', function (): void {
    $definition = CustomFieldDefinition::factory()->create(['data_type' => 'number']);

    $value = app(SetCustomFieldValueAction::class)->execute(new SetCustomFieldValueData($definition->id, 1, 42));

    expect((float) $value->value_number)->toBe(42.0)->and($value->value_text)->toBeNull();
});
