<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\ContactMail;
// use App\Models\ContactMessage; // Caso queira salvar no banco

class EmailService
{
    /**
     * Processa o formulário de contato: envia e-mail (e poderia salvar no banco).
     */
    public function processContactForm(array $data): void
    {
        // 1. (Opcional) Poderia salvar no banco aqui:
        // ContactMessage::create($data);

        // 2. Define o destinatário (Admin do sistema)
        // $adminEmail = config('mail.from.address'); // Ou 'admin@empresa.com'
        // $to_email = $data['email'];
        $to_email = 'al.gustavo@outlook.com';
        try {
            // 3. Envia o e-mail
            // Se usar filas, o envio será assíncrono automaticamente se o Mailable implementar ShouldQueue
            Mail::to($to_email)->send(new ContactMail($data));
        } catch (\Exception $e) {
            // Lida com erros de envio, se necessário
            throw new \Exception('Erro ao enviar e-mail: ' . $e->getMessage());
        }
    }

    /**
     * Envia um e-mail transacional de agendamento para o próprio sócio.
     *
     * Diferente do formulário de contato, aqui o destinatário é sempre quem
     * reservou — nunca um endereço fixo. E o envio é "best effort": um sócio
     * sem e-mail cadastrado, ou uma falha de SMTP/fila, não pode derrubar o
     * agendamento nem o pagamento que já foram gravados. O problema vai para o
     * log e o fluxo segue.
     *
     * @return bool se a mensagem chegou a ser despachada
     */
    public function sendScheduleMail(array $data): bool
    {
        $to_email = trim((string) ($data['email'] ?? ''));

        if ($to_email === '' || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
            Log::warning('E-mail de agendamento não enviado: sócio sem endereço válido.', [
                'type' => $data['type'] ?? null,
                'email' => $data['email'] ?? null,
                'schedule_ids' => $data['schedule_ids'] ?? null,
            ]);

            return false;
        }

        try {
            Mail::to($to_email)->send(new ContactMail($data));
        } catch (\Throwable $e) {
            Log::error('Falha ao enviar e-mail de agendamento.', [
                'type' => $data['type'] ?? null,
                'email' => $to_email,
                'schedule_ids' => $data['schedule_ids'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        return true;
    }
}
