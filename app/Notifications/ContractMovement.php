<?php

namespace App\Notifications;

use App\Models\SignatureContract;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Movimentação de um contrato (05/11/2026), no sino e no navegador — o mesmo
 * caminho dos avisos (canal `database`; o layout consulta
 * `notifications.unread-json` e mostra a notificação do navegador).
 *
 * `kind` (ContractNotifier::KINDS): submitted, returned, approved, adjusted,
 * refused, signed, canceled, expiring.
 */
class ContractMovement extends Notification
{
    use Queueable;

    public function __construct(
        public SignatureContract $contract,
        public string $kind,
        public string $title,
        public string $message,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'contract_' . $this->kind,
            'contract_id' => $this->contract->id,
            'title' => $this->title,
            'message' => $this->message,
            'url' => route('contracts.show', $this->contract, false),
        ];
    }
}
