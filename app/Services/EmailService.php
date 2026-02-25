<?php

namespace App\Services;

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
        $to_email = $data['email'];
        try {
            // 3. Envia o e-mail
            // Se usar filas, o envio será assíncrono automaticamente se o Mailable implementar ShouldQueue
            Mail::to($to_email)->send(new ContactMail($data));
        } catch (\Exception $e) {
            // Lida com erros de envio, se necessário
            throw new \Exception('Erro ao enviar e-mail: ' . $e->getMessage());
        }
    }
}