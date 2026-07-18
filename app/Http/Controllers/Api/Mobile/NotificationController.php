<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Get paginated notifications for the authenticated user.
     */
    public function index(Request $request)
    {
        $notifications = $request->user()->notifications()
            ->paginate(15);

        $unreadCount = $request->user()->notifications()
            ->whereNull('read_at')
            ->count();

        $data = $notifications->toArray();
        $data['unread_count'] = $unreadCount;

        return response()->json($data);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Request $request, $id)
    {
        $notification = $request->user()->notifications()
            ->findOrFail($id);

        $notification->update([
            'read_at' => now(),
        ]);

        return response()->json([
            'message' => 'Notification marked as read.',
            'notification' => $notification,
        ]);
    }

    /**
     * Mark all notifications for the user as read.
     */
    public function markAllAsRead(Request $request)
    {
        $request->user()->notifications()
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
            ]);

        return response()->json([
            'message' => 'All notifications marked as read.',
        ]);
    }
}
