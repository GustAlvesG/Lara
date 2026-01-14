<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // Tabela para quem está conversando com você
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('wa_id')->unique(); // O número do telefone (ID do WhatsApp)
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts');
            $table->foreignId('user_id')->nullable(); // ID do Atendente (tabela users do Laravel)
            $table->enum('status', ['open', 'closed', 'pending'])->default('open');
            
            // Regra das 24h do WhatsApp:
            // A empresa só pode responder livremente até 24h após a última mensagem do cliente.
            $table->timestamp('session_expires_at')->nullable(); 
            
            $table->timestamps();
        });

        // Tabela de mensagens
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts');
            $table->string('wam_id')->unique(); // ID único da mensagem gerado pelo WhatsApp
            $table->string('type')->default('text'); // text, image, video...
            $table->text('body')->nullable();
            $table->enum('direction', ['inbound', 'outbound']); // Recebida ou Enviada
            $table->string('status')->default('sent'); // sent, delivered, read
            $table->timestamp('sent_at')->nullable();
            //Foreign key para conversação
            $table->foreignId('conversation_id')->constrained('conversations')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('media_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('messages')->onDelete('cascade');
            $table->string('file_type'); // image, video, audio, document, sticker
            $table->string('mime_type'); // image/jpeg, audio/ogg
            $table->string('file_path'); // Caminho no seu storage (ex: /storage/whatsapp/image.jpg)
            $table->string('file_name')->nullable(); // Nome original do arquivo
            $table->string('whatsapp_media_id')->nullable(); // ID original da Meta (útil para reenvio sem upload)
            $table->timestamps();
        });

        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // ex: 'hello_world'
            $table->string('language'); // ex: 'pt_BR'
            $table->string('category'); // ex: 'MARKETING', 'UTILITY'
            $table->text('body'); // O texto do template com variáveis {{1}}, {{2}}
            $table->string('status'); // approved, rejected, pending
            $table->timestamps();
        });

        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->json('payload'); // O JSON inteiro recebido
            $table->boolean('processed')->default(false); // Se seu sistema conseguiu ler
            $table->text('error_message')->nullable(); // Caso tenha falhado
            $table->timestamps();
        });


    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('media_attachments');
        Schema::dropIfExists('templates');
        Schema::dropIfExists('webhook_logs');
    }
};
