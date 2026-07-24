<?php

namespace App\Notifications;

use App\Models\Aviso;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AvisoExpiring extends Notification
{
    use Queueable;

    public function __construct(public Aviso $aviso) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'aviso_expiring',
            'aviso_id' => $this->aviso->id,
            'title' => $this->aviso->title,
            'message' => 'Aviso expirado: ' . $this->aviso->title,
            'url' => route('avisos.show', $this->aviso->id, false),
        ];
    }
}
