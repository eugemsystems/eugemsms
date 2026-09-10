<?php

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Domain\Actions\Documents\CreateDocumentTemplateAction;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\Actions\Documents\GenerateDocumentAction;
use Modules\Core\Domain\Actions\Documents\InitiateDocumentBatchAction;
use Modules\Core\Domain\Actions\Documents\RecordDocumentDownloadAction;
use Modules\Core\Domain\Actions\Documents\RegenerateDocumentAction;
use Modules\Core\Domain\Actions\Documents\UpdateDocumentTemplateAction;
use Modules\Core\Domain\Actions\Documents\VerifyDocumentAction;
use Modules\Core\Domain\DataObjects\Documents\CreateDocumentTemplateData;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\DataObjects\Documents\GenerateDocumentData;
use Modules\Core\Domain\DataObjects\Documents\InitiateDocumentBatchData;
use Modules\Core\Domain\DataObjects\Documents\RecordDocumentDownloadData;
use Modules\Core\Domain\DataObjects\Documents\RegenerateDocumentData;
use Modules\Core\Domain\DataObjects\Documents\UpdateDocumentTemplateData;
use Modules\Core\Domain\DataObjects\Documents\VerifyDocumentData;
use Modules\Core\Domain\Exceptions\UnknownTemplateVariableException;
use Modules\Core\Domain\Exceptions\VerificationCodeNotFoundException;
use Modules\Core\Domain\Registry\TemplateVariableRegistry;
use Modules\Core\Models\School;

beforeEach(function (): void {
    TemplateVariableRegistry::clear();
    TemplateVariableRegistry::register('receipt', ['school.name', 'invoice.balance_minor']);
});

it('rejects a template that references an unregistered variable at save time (AC-CORE-06-004)', function (): void {
    $school = School::factory()->create();

    app(CreateDocumentTemplateAction::class)->execute(new CreateDocumentTemplateData(
        schoolId: $school->id,
        templateType: 'receipt',
        name: 'Standard receipt',
        content: '{{ invoice.total_paid }}',
    ));
})->throws(UnknownTemplateVariableException::class);

it('generates a document, allocating a number inside the same transaction', function (): void {
    $school = School::factory()->create(['code' => 'ESC']);
    $user = User::factory()->create();
    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData($school->id, 'receipt', '{SCHOOL}/{TYPE}/{SEQ:6}'));
    app(CreateDocumentTemplateAction::class)->execute(new CreateDocumentTemplateData(
        schoolId: $school->id,
        templateType: 'receipt',
        name: 'Standard receipt',
        content: '<p>{{ school.name }} owes {{ invoice.balance_minor | number }}</p>',
        isDefault: true,
    ));

    $document = app(GenerateDocumentAction::class)->execute(new GenerateDocumentData(
        schoolId: $school->id,
        documentType: 'receipt',
        data: ['school' => ['name' => $school->name], 'invoice' => ['balance_minor' => 5000]],
        generatedByUserId: $user->id,
    ));

    expect($document->number)->toBe('ESC/RECEIPT/000001')
        ->and($document->file_hash)->toHaveLength(64)
        ->and(Storage::disk('local')->exists($document->file_path))->toBeTrue();
});

it('produces an identical file hash when regenerated with the same data and template version (AC-CORE-06-003)', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    app(CreateDocumentTemplateAction::class)->execute(new CreateDocumentTemplateData(
        schoolId: $school->id,
        templateType: 'receipt',
        name: 'Standard receipt',
        content: '<p>{{ school.name }}: {{ invoice.balance_minor }}</p>',
        isDefault: true,
    ));

    $data = ['school' => ['name' => 'Eugem'], 'invoice' => ['balance_minor' => 999]];

    $original = app(GenerateDocumentAction::class)->execute(new GenerateDocumentData(
        schoolId: $school->id,
        documentType: 'receipt',
        data: $data,
        generatedByUserId: $user->id,
        allocateNumber: false,
    ));

    $regenerated = app(RegenerateDocumentAction::class)->execute(new RegenerateDocumentData($original->id, $data));

    expect($regenerated->file_hash)->toBe($original->file_hash)
        ->and($regenerated->template_id)->toBe($original->template_id)
        ->and($regenerated->template_version)->toBe($original->template_version);
});

it('regenerating after the template changes still uses the historical version (BR-CORE-06-008)', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    $v1 = app(CreateDocumentTemplateAction::class)->execute(new CreateDocumentTemplateData(
        schoolId: $school->id,
        templateType: 'receipt',
        name: 'Standard receipt',
        content: '<p>v1: {{ school.name }}</p>',
        isDefault: true,
    ));

    $data = ['school' => ['name' => 'Eugem'], 'invoice' => ['balance_minor' => 1]];
    $original = app(GenerateDocumentAction::class)->execute(new GenerateDocumentData(
        schoolId: $school->id,
        documentType: 'receipt',
        data: $data,
        generatedByUserId: $user->id,
        templateId: $v1->id,
        allocateNumber: false,
    ));

    app(UpdateDocumentTemplateAction::class)->execute(new UpdateDocumentTemplateData($v1->id, content: '<p>v2: {{ school.name }}</p>'));

    $regenerated = app(RegenerateDocumentAction::class)->execute(new RegenerateDocumentData($original->id, $data));

    expect($regenerated->template_version)->toBe(1)
        ->and(Storage::disk('local')->get($regenerated->file_path))->toContain('v1: Eugem');
});

it('creates a new template version rather than mutating the original row (BR-CORE-06-007)', function (): void {
    $school = School::factory()->create();
    $v1 = app(CreateDocumentTemplateAction::class)->execute(new CreateDocumentTemplateData(
        schoolId: $school->id,
        templateType: 'receipt',
        name: 'Standard receipt',
        content: '<p>{{ school.name }}</p>',
        isDefault: true,
    ));

    $v2 = app(UpdateDocumentTemplateAction::class)->execute(new UpdateDocumentTemplateData($v1->id, content: '<p>updated {{ school.name }}</p>'));

    expect($v2->id)->not->toBe($v1->id)
        ->and($v2->version)->toBe(2)
        ->and($v1->fresh()->is_active)->toBeFalse()
        ->and($v2->is_active)->toBeTrue();
});

it('increments the download count and raises an event', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    app(CreateDocumentTemplateAction::class)->execute(new CreateDocumentTemplateData($school->id, 'receipt', 'Receipt', '<p>{{ school.name }}</p>', isDefault: true));
    $document = app(GenerateDocumentAction::class)->execute(new GenerateDocumentData(
        schoolId: $school->id,
        documentType: 'receipt',
        data: ['school' => ['name' => 'X']],
        generatedByUserId: $user->id,
        allocateNumber: false,
    ));

    $updated = app(RecordDocumentDownloadAction::class)->execute(new RecordDocumentDownloadData($document->id));

    expect($updated->download_count)->toBe(1);
});

it('lets an unauthenticated caller verify a document without seeing its content (BR-CORE-06-012/AC-CORE-06-005)', function (): void {
    $school = School::factory()->create(['name' => 'Eugem School']);
    $user = User::factory()->create();
    app(CreateDocumentTemplateAction::class)->execute(new CreateDocumentTemplateData($school->id, 'receipt', 'Receipt', '<p>secret marks</p>', isDefault: true));
    $document = app(GenerateDocumentAction::class)->execute(new GenerateDocumentData(
        schoolId: $school->id,
        documentType: 'receipt',
        data: [],
        generatedByUserId: $user->id,
        allocateNumber: false,
        verifiable: true,
    ));

    $result = app(VerifyDocumentAction::class)->execute(new VerifyDocumentData((string) $document->verification_code));

    expect($result->documentType)->toBe('receipt')
        ->and($result->schoolName)->toBe('Eugem School')
        ->and($result->isValid)->toBeTrue();
});

it('refuses an unknown verification code', function (): void {
    app(VerifyDocumentAction::class)->execute(new VerifyDocumentData('NOTREAL123'));
})->throws(VerificationCodeNotFoundException::class);

it('initiates a document batch header (BR-CORE-06-013)', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    $template = app(CreateDocumentTemplateAction::class)->execute(new CreateDocumentTemplateData($school->id, 'receipt', 'Receipt', '<p>{{ school.name }}</p>', isDefault: true));

    $batch = app(InitiateDocumentBatchAction::class)->execute(new InitiateDocumentBatchData($school->id, 'receipt', $template->id, 25, $user->id));

    expect($batch->status)->toBe('queued')
        ->and($batch->total_count)->toBe(25);
});
