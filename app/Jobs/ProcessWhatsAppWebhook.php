<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WebhookLog;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessWhatsAppWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function handle(WhatsAppService $whatsappService)
    {
        // 1. Logar o payload bruto para segurança e debug
        $log = WebhookLog::create(['payload' => json_encode($this->payload)]);

        try {
            $entry = $this->payload['entry'][0] ?? null;
            $changes = $entry['changes'][0]['value'] ?? null;

            // Se não houver mensagens (pode ser status update), encerra.
            if (!isset($changes['messages'])) {
                $log->update(['processed' => true]);
                return;
            }

            $messageData = $changes['messages'][0];
            $contactData = $changes['contacts'][0] ?? null;
            $waId = $contactData['wa_id'];

            DB::transaction(function () use ($messageData, $contactData, $waId, $whatsappService) {
                // 2. Gerenciar Contato
                $contact = Contact::firstOrCreate(
                    ['wa_id' => $waId],
                    ['name' => $contactData['profile']['name'] ?? 'Desconhecido']
                );

                // 3. Gerenciar Conversa (Ticket)
                // Procura conversa aberta, senão cria uma nova
                $conversation = $contact->activeConversation;
                
                if (!$conversation) {
                    $conversation = Conversation::create([
                        'contact_id' => $contact->id,
                        'status' => 'open',
                        'last_message_at' => now()
                    ]);
                } else {
                    $conversation->touch('last_message_at');
                }

                // 4. Salvar Mensagem Base
                $type = $messageData['type'];
                $body = null;

                if ($type === 'text') {
                    $body = $messageData['text']['body'];
                } elseif ($type === 'button') {
                    $body = $messageData['button']['text'];
                }

                $message = Message::create([
                    'conversation_id' => $conversation->id,
                    'wam_id' => $messageData['id'],
                    'type' => $type,
                    'direction' => 'inbound',
                    'body' => $body,
                    'status' => 'received'
                ]);

                // 5. Processar Mídia (Imagem, Audio, Documento)
                if (in_array($type, ['image', 'audio', 'video', 'document', 'sticker', 'voice'])) {
                    
                    // O JSON varia ligeiramente. Ex: $messageData['image']['id']
                    $mediaId = $messageData[$type]['id'] ?? null;
                    
                    if ($mediaId) {
                        // Baixa o arquivo usando o Service
                        $fileData = $whatsappService->downloadMedia($mediaId);

                        if ($fileData) {
                            $message->media()->create([
                                'whatsapp_media_id' => $mediaId,
                                'file_type' => $type,
                                'mime_type' => $fileData['mime'],
                                'file_path' => $fileData['path'],
                                'file_name' => $messageData[$type]['filename'] ?? null // PDFs costumam ter nome
                            ]);
                        }
                    }
                }
            });

            $log->update(['processed' => true]);

        } catch (\Exception $e) {
            Log::error("Erro no Webhook WhatsApp: " . $e->getMessage());
            $log->update(['error_message' => $e->getMessage()]);
            // Opcional: throw $e; para tentar novamente na fila (retry)
        }
    }
}