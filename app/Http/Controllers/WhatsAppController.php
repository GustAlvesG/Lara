<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\ProcessWhatsAppWebhook;
use App\Services\WhatsAppService;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;

class WhatsAppController extends Controller
{
    // Verificação do Webhook (Meta Challenge)
    public function verify(Request $request)
    {
        if (
            $request->hub_mode === 'subscribe' && 
            $request->hub_verify_token === env('WHATSAPP_VERIFY_TOKEN')
        ) {
            return response($request->hub_challenge, 200);
        }
        return response()->json(['error' => 'Forbidden'], 403);
    }

    // Recebimento de Eventos
    public function handle(Request $request)
    {
        // Responde rápido para o WhatsApp não reenviar
        ProcessWhatsAppWebhook::dispatch($request->all());
        return response('OK', 200);
    }

    // Exemplo de Envio: POST /api/whatsapp/send
    public function sendMessage(Request $request, WhatsAppService $service)
    {
        $request->validate([
            'phone' => 'required', // ID do contato no seu banco ou numero WA
            'message' => 'required'
        ]);

        // Aqui você deveria buscar o Contact pelo ID e ver se há conversa aberta
        // Mas para simplificar, envio direto:
        
        $response = $service->sendText($request->phone, $request->message);

        if (isset($response['messages'][0]['id'])) {
            // Salvar a mensagem enviada (Outbound) no banco
            // Você precisará encontrar a conversation_id correta aqui
            
            return response()->json(['status' => 'success', 'data' => $response]);
        }

        return response()->json(['status' => 'error', 'data' => $response], 500);
    }
}