<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UberAccessRequestMessage extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'uber_access_request_id',
        'poli_message_id',
        'poli_sequence',
        'contact_uuid',
        'raw_payload',
        'processed_at',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'processed_at' => 'datetime',
    ];

    public function uberAccessRequest()
    {
        return $this->belongsTo(UberAccessRequest::class);
    }

    /**
     * Existe outra mensagem do mesmo contato que a Poli enviou ANTES desta e
     * que ainda não passou pelo fluxo?
     *
     * Se existir, esta aqui tem de ceder a vez: o webhook entrega fora de
     * ordem, e processar na ordem de chegada joga a resposta no campo errado.
     *
     * A janela é o freio de mão. Sem ela, uma mensagem que nunca conclui
     * (presa em retentativa, ou um payload que o fluxo não digere) seguraria
     * todas as seguintes daquele contato para sempre. Passado o prazo, segue
     * sem ela — melhor uma ordem imperfeita do que uma conversa parada.
     */
    public function hasPendingPredecessor(int $windowSeconds): bool
    {
        // Mensagens anteriores à migration não têm sequência, e NULL nunca casa
        // com `<`: elas não seguram ninguém, que é o comportamento desejado.
        if ($this->contact_uuid === null || $this->poli_sequence === null) {
            return false;
        }

        return static::query()
            ->where('contact_uuid', $this->contact_uuid)
            ->where('poli_sequence', '<', $this->poli_sequence)
            ->whereNull('processed_at')
            ->where('created_at', '>=', now()->subSeconds($windowSeconds))
            ->exists();
    }

    /**
     * Ainda há mensagem deste atendimento esperando na fila?
     *
     * É a pergunta que o fecho da coleta precisa fazer antes de encerrar. A
     * despedida do bot sai por um caminho síncrono e as respostas do associado
     * por um caminho com atraso: fechar sem olhar para trás mata exatamente as
     * respostas que ainda estavam em voo — o pedido some com metade dos campos
     * preenchidos, embora a conversa tenha sido perfeita.
     *
     * Só mensagens de ENTRADA ficam pendentes: as de saída já nascem marcadas
     * no próprio webhook.
     */
    public static function hasPendingForAttendance(string $attendanceUuid, int $windowSeconds): bool
    {
        return static::query()
            ->whereNull('processed_at')
            ->where('created_at', '>=', now()->subSeconds($windowSeconds))
            ->where('raw_payload->value->attendance->uuid', $attendanceUuid)
            ->exists();
    }

    /**
     * Marca a mensagem como resolvida — tenha ela alimentado o fluxo, sido
     * recusada ou ignorada. O que importa para a ordem é que ela saiu do
     * caminho das seguintes.
     */
    public function markProcessed(?int $uberAccessRequestId = null): void
    {
        if ($uberAccessRequestId !== null) {
            $this->uber_access_request_id = $uberAccessRequestId;
        }

        $this->processed_at = now();
        $this->save();
    }
}
