<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WhatsAppService
{
    protected $baseUrl;
    protected $token;
    protected $phoneId;

    public function __construct()
    {
        $this->baseUrl = env('WHATSAPP_API_URL', 'https://graph.facebook.com/v18.0');
        $this->token = env('WHATSAPP_TOKEN');
        $this->phoneId = env('WHATSAPP_PHONE_ID');
    }

    /**
     * Envia mensagem de texto simples
     */
    public function sendText($to, $message)
    {
        return $this->sendRequest([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $message]
        ]);
    }

    /**
     * Baixa a mídia do WhatsApp e salva no Storage local
     */
    public function downloadMedia($mediaId)
    {
        // 1. Obter a URL de download
        $response = Http::withToken($this->token)->get("{$this->baseUrl}/{$mediaId}");
        
        if ($response->failed()) return null;
        
        $data = $response->json();
        $url = $data['url'] ?? null;
        $mime = $data['mime_type'] ?? '';
        
        if (!$url) return null;

        // 2. Baixar o binário (Atenção: A URL de download requer o Token Authorization)
        $fileContent = Http::withToken($this->token)->get($url)->body();
        
        // 3. Gerar nome e salvar
        $extension = $this->getExtensionFromMime($mime);
        $fileName = "whatsapp_media/{$mediaId}.{$extension}";
        
        Storage::disk('public')->put($fileName, $fileContent);

        return [
            'path' => $fileName,
            'mime' => $mime,
            'extension' => $extension
        ];
    }

    protected function sendRequest($data)
    {
        return Http::withToken($this->token)
            ->post("{$this->baseUrl}/{$this->phoneId}/messages", $data)
            ->json();
    }

    private function getExtensionFromMime($mime)
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'audio/ogg' => 'ogg',
            'application/pdf' => 'pdf',
            'video/mp4' => 'mp4'
        ];
        return $map[$mime] ?? 'bin';
    }
}