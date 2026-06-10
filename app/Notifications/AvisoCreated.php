<?php

namespace App\Notifications;

use App\Models\Aviso;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AvisoCreated extends Notification
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
            'type' => 'aviso_created',
            'aviso_id' => $this->aviso->id,
            'title' => $this->aviso->title,
            'message' => 'Novo aviso: ' . $this->aviso->title,
            'url' => route('avisos.show', $this->aviso->id),
        ];
    }
}
