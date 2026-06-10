<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function unreadJson(Request $request): JsonResponse
    {
        $since = $request->query('since')
            ? Carbon::parse($request->query('since'))
            : now()->subSeconds(35);

        $checkedAt = now()->toISOString();

        $notifications = auth()->user()
            ->unreadNotifications()
            ->where('created_at', '>', $since)
            ->get()
            ->map(fn($n) => [
                'title'   => $n->data['title'] ?? 'Aviso',
                'message' => $n->data['message'] ?? '',
                'url'     => $n->data['url'] ?? route('avisos.index'),
            ]);

        return response()->json([
            'checked_at'    => $checkedAt,
            'notifications' => $notifications,
        ]);
    }

    public function markRead(string $id)
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $redirect = $notification->data['url'] ?? route('avisos.index');
        return redirect($redirect);
    }

    public function markAllRead()
    {
        auth()->user()->unreadNotifications->markAsRead();
        return back();
    }
}
