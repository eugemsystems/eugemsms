<?php

declare(strict_types=1);

namespace Modules\Intelligence\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Modules\Core\Domain\Exceptions\InsufficientScopeException;
use Modules\Core\Http\Support\ApiResponse;
use Modules\Intelligence\Domain\Actions\GenerateReportExportAction;
use Modules\Intelligence\Domain\Actions\GetAvailableFieldsForUserAction;
use Modules\Intelligence\Domain\Actions\RunSavedReportAction;
use Modules\Intelligence\Domain\DataObjects\ReportFieldDefinition;
use Modules\Intelligence\Domain\Registry\ReportFieldRegistry;
use Modules\Intelligence\Models\ApiClient;
use Modules\Intelligence\Models\CustomReport;
use Symfony\Component\HttpFoundation\Response;

/**
 * `/api/v1/reports/*` (Book J INT-01 §5, `serp.api-client:reports:read`).
 * A third-party integration acts as the API key's own `created_by` staff
 * user — exactly the identity `RunSavedReportAction`/
 * `GetAvailableFieldsForUserAction` already re-evaluate every field
 * against for a human viewer (BR-INT-01-005), so a key can run or export
 * only what the person who issued it could themselves see, nothing more.
 */
final class ReportsController
{
    public function __construct(
        private readonly GetAvailableFieldsForUserAction $getAvailableFields,
        private readonly RunSavedReportAction $runSavedReport,
        private readonly GenerateReportExportAction $generateExport,
    ) {}

    public function entities(Request $request): Response
    {
        $runner = $this->actingUser($request);

        $entities = [];

        foreach (ReportFieldRegistry::allEntities() as $entityKey => $entity) {
            $entities[$entityKey] = [
                'module_code' => $entity->moduleCode,
                'fields' => array_map(fn (ReportFieldDefinition $field): array => [
                    'field_key' => $field->fieldKey,
                    'label' => $field->label,
                    'data_type' => $field->dataType,
                    'is_filterable' => $field->isFilterable,
                    'is_groupable' => $field->isGroupable,
                    'is_aggregatable' => $field->isAggregatable,
                ], $this->getAvailableFields->execute($entityKey, $runner)),
            ];
        }

        return ApiResponse::ok(['entities' => $entities]);
    }

    public function run(Request $request, string $ulid): Response
    {
        $report = $this->ownReport($request, $ulid);
        $result = $this->runSavedReport->execute($report->id, $this->actingUser($request));

        return ApiResponse::ok([
            'rows' => $result->rows,
            'row_count' => $result->rowCount,
            'duration_ms' => $result->durationMs,
            'was_redirected' => $result->wasRedirected,
            'redirect_reason' => $result->redirectReason,
        ]);
    }

    public function export(Request $request, string $ulid): Response
    {
        $format = (string) $request->query('format', '');

        if (! in_array($format, ['pdf', 'excel', 'csv'], true)) {
            return ApiResponse::error('INVALID_FORMAT', 'format must be one of pdf, excel, csv.', 422);
        }

        $report = $this->ownReport($request, $ulid);
        $result = $this->runSavedReport->execute($report->id, $this->actingUser($request));

        try {
            $file = $this->generateExport->execute($result, $format, $report->name);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error('INVALID_FORMAT', $e->getMessage(), 422);
        }

        return response($file->content, 200, [
            'Content-Type' => $file->mimeType,
            'Content-Disposition' => 'attachment; filename="'.$file->filename.'"',
        ]);
    }

    private function ownReport(Request $request, string $ulid): CustomReport
    {
        return CustomReport::where('school_id', $this->client($request)->school_id)->where('ulid', $ulid)->firstOrFail();
    }

    private function actingUser(Request $request): User
    {
        $client = $this->client($request);

        return $client->createdBy ?? throw new InsufficientScopeException('This API key has no registering user to attribute report access to.');
    }

    private function client(Request $request): ApiClient
    {
        /** @var ApiClient $client */
        $client = $request->attributes->get('api_client');

        return $client;
    }
}
