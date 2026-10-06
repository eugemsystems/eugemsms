<?php

declare(strict_types=1);

namespace Modules\Intelligence\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Intelligence\Domain\Support\OpenApiDocumentBuilder;

/**
 * `GET /api/v1/openapi.json` (Book J INT-04 §5, public). The raw OpenAPI
 * document, not the `{success,data,meta}` envelope, so standard tooling can read it.
 */
final class OpenApiController
{
    public function __invoke(OpenApiDocumentBuilder $builder): JsonResponse
    {
        return response()->json($builder->build(), 200, ['Cache-Control' => 'public, max-age=300']);
    }
}
