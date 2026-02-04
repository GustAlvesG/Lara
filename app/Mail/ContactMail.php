<?php

namespace App\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $data;
    public string $type; // Nova propriedade para controlar o tipo

    public function __construct(array $data)
    {
        $this->data = $data;
        
        // Assumindo que no array $data venha um campo 'type'
        // Caso não venha, usamos 'default'
        $this->type = $data['type'] ?? 'default';
        // dd($this->type, $this->data, $data['type']);
    }

    public function envelope(): Envelope
    {
        // Podemos até mudar o assunto dinamicamente também!
        $subjectPrefix = match ($this->type) {
            'schedule.confirm'   => '[CONFIRMADO]',
            'schedule.pending' => '[PENDENTE]',
            default   => '[Contato]',
        };

        return new Envelope(
            subject: $subjectPrefix . ' ' . ($this->data['subject'] ?? 'Novo E-mail'),
        );
    }

    /**
     * AQUI ACONTECE A MÁGICA DA VIEW DINÂMICA
     */
    public function content(): Content
    {
        // Mapeia o 'tipo' recebido para o caminho do arquivo blade
        $viewName = match ($this->type) {
            'schedule.confirm'   => 'emails.schedule.confirm',   // resources/views/emails/confirm_schedule.blade.php
            'schedule.pending' => 'emails.schedule.pending', // resources/views/emails/pending_schedule.blade.php
            'job'     => 'emails.hr_template',      // resources/views/emails/hr_template.blade.php
            default   => 'emails.general_contact',  // template padrão
        };
        
        return new Content(
            view: $viewName,
        );
    }
}