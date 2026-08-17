<?php

namespace App\Http\Controllers\Api\Notification;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Notification\ListNotificationsRequest;
use App\Http\Resources\Api\Notification\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(ListNotificationsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $query = $request->user()->notifications()->getQuery()
            ->when(($validated['status'] ?? 'all') === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->when(($validated['status'] ?? 'all') === 'read', fn ($query) => $query->whereNotNull('read_at'))
            ->when(isset($validated['type']), fn ($query) => $query->where('type', $validated['type']))
            ->orderByDesc('created_at')
            ->orderByDesc('id');
        $paginator = $query->cursorPaginate((int) ($validated['perPage'] ?? 20));

        return response()->json([
            'success' => true,
            'data' => [
                'notifications' => NotificationResource::collection($paginator->items())->resolve($request),
                'unreadCount' => $request->user()->unreadNotifications()->count(),
                'pagination' => [
                    'perPage' => $paginator->perPage(),
                    'hasMore' => $paginator->hasMorePages(),
                    'nextCursor' => $paginator->nextCursor()?->encode(),
                    'previousCursor' => $paginator->previousCursor()?->encode(),
                ],
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['unreadCount' => $request->user()->unreadNotifications()->count()],
        ]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $model = $this->ownedNotification($request, $notification);
        $model->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
            'data' => ['notification' => (new NotificationResource($model->refresh()))->resolve($request)],
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $updated = $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read.',
            'data' => ['updatedCount' => $updated, 'unreadCount' => 0],
        ]);
    }

    public function destroy(Request $request, string $notification): JsonResponse
    {
        $this->ownedNotification($request, $notification)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted.',
            'data' => ['deleted' => true],
        ]);
    }

    private function ownedNotification(Request $request, string $id): DatabaseNotification
    {
        return $request->user()->notifications()->whereKey($id)->firstOrFail();
    }
}
