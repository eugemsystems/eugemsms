<?php

declare(strict_types=1);

namespace Modules\Comms\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Domain\Actions\Notifications\MarkNotificationReadAction;
use Modules\Core\Http\Support\ApiResponse;
use Modules\Core\Models\Notification;
use Modules\People\Models\Guardian;

/**
 * `/api/v1/communications/inbox` (Volume 1 §9.3). The in-app copies of messages the school sent
 * to the signed-in guardian, newest first, with an unread count for the app badge. A guardian
 * only ever reads their own.
 */
final class InboxController
{
    public function index(Request $request): JsonResponse
    {
        $query = $this->own($request)->where('channel', 'in_app')->orderByDesc('id');
        $unread = (clone $query)->whereNull('read_at')->count();
        $page = $query->paginate(ApiResponse::perPage($request->integer('per_page') ?: null));

        return ApiResponse::ok($page->getCollection()->map(fn (Notification $n): array => [
            'id' => $n->ulid,
            'title' => $n->subject,
            'body' => $n->body,
            'key' => $n->notification_key,
            'read' => $n->read_at !== null,
            'received_at' => $n->created_at->toIso8601ZuluString(),
        ])->values()->all(), [
            'unread_count' => $unread,
            'pagination' => ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()],
        ]);
    }

    public function read(Request $request, string $notification, MarkNotificationReadAction $mark): JsonResponse
    {
        $found = $this->own($request)->where('ulid', $notification)->first();
        abort_if($found === null, 404);

        $mark->execute($found);

        return ApiResponse::ok(['read' => true]);
    }

    /**
     * @return Builder<Notification>
     */
    private function own(Request $request): Builder
    {
        /** @var User $user */
        $user = $request->user();

        return Notification::query()->where('recipient_type', 'guardian')->whereIn('recipient_id', Guardian::query()->where('user_id', $user->id)->pluck('id'));
    }
}
