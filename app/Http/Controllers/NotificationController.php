<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * GET /api/v1/notifications
     * Daftar notifikasi milik user yang sedang login, terbaru di atas.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($n) => [
                'id'         => $n->id,
                'type'       => $n->type,
                'data'       => $n->data,
                'read_at'    => optional($n->read_at)->toIso8601String(),
                'created_at' => optional($n->created_at)->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Notifications retrieved successfully',
            'data'    => $notifications,
        ]);
    }

    /**
     * PUT|POST /api/v1/notifications/{id}/read
     * Tandai notifikasi sebagai sudah dibaca. User hanya boleh menandai
     * notifikasi miliknya sendiri.
     */
    public function markAsRead(Request $request, string $id)
    {
        $user = $request->user();

        $notification = $user->notifications()->where('id', $id)->first();

        if (! $notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found',
            ], 404);
        }

        if (is_null($notification->read_at)) {
            $notification->markAsRead();
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
        ]);
    }
}
