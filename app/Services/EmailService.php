<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use App\Mail\ContactMail;
use Exception;

class EmailService
{
    /**
     * Envia o e-mail de contato para o administrador.
     *
     * @param array $data
     * @return bool
     * @throws Exception
     */
    public function sendContactEmail(array $data): bool
    {
        // Aqui você pode colocar lógicas adicionais antes de enviar.
        // Ex: Salvar o contato no banco de dados antes de enviar o e-mail.
        
        // Destinatário fixo (ex: admin) ou dinâmico
        $recipient = 'admin@seuapp.com';

        try {
            // Dispara o Mailable criado anteriormente
            Mail::to($recipient)->send(new ContactMail($data));
            return true;
        } catch (Exception $e) {
            // Opcional: Logar o erro aqui
            // Log::error("Erro ao enviar email: " . $e->getMessage());
            throw $e; // Relança a exceção para o Controller tratar
        }
    }
}