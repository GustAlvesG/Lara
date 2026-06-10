<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function unreadJson(Request $request): JsonResponse
    {
        // O navegador envia `since` em UTC (new Date().toISOString() -> "...Z").
        // Como created_at é gravado no fuso da aplicação (America/Sao_Paulo),
        // precisamos converter antes de comparar, senão a query erra em ~3h.
        $since = $request->query('since')
            ? Carbon::parse($request->query('since'))->setTimezone(config('app.timezone'))
            : now()->subSeconds(35);

        $checkedAt = now()->toISOString();

        $notifications = auth()->user()
            ->unreadNotifications()
            ->where('created_at', '>', $since)
            ->get()
            ->map(fn($n) => [
                'title'   => $n->data['title'] ?? 'Aviso',
                'message' => $n->data['message'] ?? '',
                'url'     => $this->relativeUrl($n->data['url'] ?? null),
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

        return redirect($this->relativeUrl($notification->data['url'] ?? null));
    }

    /**
     * Converte qualquer URL guardada na notificação em um caminho relativo
     * (path + query). Assim o redirect/clique resolve sempre no host atual,
     * mesmo para notificações antigas que gravaram "http://localhost/...".
     */
    private function relativeUrl(?string $url): string
    {
        if (!$url) {
            return route('avisos.index', absolute: false);
        }

        $path  = parse_url($url, PHP_URL_PATH) ?: '/';
        $query = parse_url($url, PHP_URL_QUERY);

        return $path . ($query ? '?' . $query : '');
    }

    public function markAllRead()
    {
        auth()->user()->unreadNotifications->markAsRead();
        return back();
    }
}
