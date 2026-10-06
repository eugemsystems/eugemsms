<?php

declare(strict_types=1);

namespace Modules\People\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Academic\Models\TermResult;
use Modules\Core\Domain\Actions\Documents\RecordDocumentDownloadAction;
use Modules\Core\Domain\DataObjects\Documents\RecordDocumentDownloadData;
use Modules\Core\Http\Support\ApiResponse;
use Modules\Core\Models\Document;
use Modules\People\Domain\Support\LinkedLearners;
use Modules\People\Models\Student;
use Symfony\Component\HttpFoundation\Response;

/**
 * `GET /api/v1/students/{student}/documents` and `.../documents/{document}/download` (Book A
 * CORE-06 §9, Volume 1 §9.3). The generated documents a guardian (or the learner) may fetch for a
 * learner they are currently linked to: those issued against the learner themselves (transcripts,
 * ID cards) and the learner's PUBLISHED report cards. A report card withheld for fees, a draft, an
 * expired document and anything issued against another learner never appear, and the download
 * resolves the document through the same scoped query, so a guessed ulid is a 404. The file is
 * streamed from private storage; its path is never exposed.
 */
final class GuardianDocumentsController
{
    public function index(Request $request, string $student, LinkedLearners $linked): JsonResponse
    {
        $link = $linked->linkFor($this->user($request), $student);
        abort_if($link === null, 404);

        $page = $this->visible($link->student)
            ->orderByDesc('generated_at')->orderByDesc('id')
            ->paginate(ApiResponse::perPage($request->integer('per_page') ?: null));

        return ApiResponse::page($page->getCollection()->map(fn (Document $document): array => [
            'id' => $document->ulid,
            'type' => $document->document_type,
            'number' => $document->number,
            'size_bytes' => $document->file_size,
            'generated_at' => $document->generated_at->toIso8601ZuluString(),
            'expires_at' => $document->expires_at?->toIso8601ZuluString(),
        ])->values()->all(), $page);
    }

    public function download(Request $request, string $student, string $document, LinkedLearners $linked): Response
    {
        $link = $linked->linkFor($this->user($request), $student);
        abort_if($link === null, 404);

        $found = $this->visible($link->student)->where('ulid', $document)->first();
        abort_if($found === null, 404);

        if (! Storage::disk(config('filesystems.documents_disk'))->exists($found->file_path)) {
            return ApiResponse::error('NOT_FOUND', 'This document\'s file is not available.', 404);
        }

        app(RecordDocumentDownloadAction::class)->execute(new RecordDocumentDownloadData($found->id));

        $extension = pathinfo($found->file_path, PATHINFO_EXTENSION);
        $filename = trim("{$found->document_type}-{$found->number}", '-').'.'.$extension;

        return Storage::disk(config('filesystems.documents_disk'))->download($found->file_path, $filename);
    }

    /**
     * @return Builder<Document>
     */
    private function visible(Student $student): Builder
    {
        $resultIds = TermResult::query()->where('student_id', $student->id)->where('status', 'published')->select('id');

        return Document::query()
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $q) => $q->where('documentable_type', $student->getMorphClass())->where('documentable_id', $student->id))
                ->orWhere(fn (Builder $q) => $q->where('documentable_type', (new TermResult)->getMorphClass())->whereIn('documentable_id', $resultIds)));
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
