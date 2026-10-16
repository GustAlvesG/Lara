<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Uma linha do diário dos atendimentos do O Lara: a conversa chegou a ele
 * (`recebida`), saiu dele (`enviada`) ou foi encerrada (`encerramento`), e
 * por quê. Quem escreve é App\Services\PoliBot\AttendanceJournal.
 */
class BotAttendanceEvent extends Model
{
    public const RECEBIDA = 'recebida';
    public const ENVIADA = 'enviada';
    public const ENCERRAMENTO = 'encerramento';

    /** Resultado de uma enviada pelo O Lara. */
    public const AGUARDANDO = 'aguardando';
    public const ATENDENTE = 'atendente';
    public const FILA = 'fila';
    public const DEVOLVIDO = 'devolvido';
    public const FALHOU = 'falhou';

    /**
     * Motivo => rótulo da tela, por tipo. A chave é o que vai no banco.
     *
     * @var array<string, array<string, string>>
     */
    public const REASONS = [
        self::RECEBIDA => [
            'entrada' => 'Entrada pela Poli (bot ou distribuição automática)',
            'retorno_transbordo' => 'A Poli devolveu o transbordo para O Lara',
            'atendente' => 'Um atendente transferiu para O Lara',
            'resgate' => 'Resgate de mensagem presa em atendimento que O Lara encerrou',
            'aviso_carro' => 'Aviso de chegada do carro de aplicativo',
            'api' => 'Transferida pela API (O Lara)',
        ],
        self::ENVIADA => [
            'fluxo' => 'Passo de transbordo do fluxo',
            'pediu_atendente' => 'O contato pediu atendente',
            'tentativas' => 'Respostas inválidas demais',
            'piloto' => 'Número fora da lista de teste (piloto)',
            'devolucao' => 'Devolução manual (poli:bot-reconciliar --devolver)',
            'retirada' => 'Tirada do O Lara no meio da conversa',
        ],
        self::ENCERRAMENTO => [
            'fim_do_fluxo' => 'Fluxo concluído e o contato não escreveu mais',
            'transbordo_devolvido' => 'Transbordo devolvido pela Poli; contato avisado',
            'transbordo_falhou' => 'Transbordo não saiu do O Lara; contato avisado',
            'abandono' => 'O contato parou de responder no meio do fluxo',
            'pergunta_opcional' => 'Fluxo concluído; pergunta final sem resposta',
            'sair' => 'O contato pediu para sair',
            'acao_do_fluxo' => 'Passo "encerrar" do fluxo',
            'tentativas' => 'Respostas inválidas demais',
            'reconciliacao' => 'Conversa com O Lara sem condução (reconciliação)',
            'aviso_carro' => 'Depois do aviso de chegada do carro de aplicativo',
            'atendente' => 'Encerrado por um atendente',
            'api' => 'Encerrado pela API (O Lara)',
            'sistema' => 'Encerrado pela Poli',
        ],
    ];

    public const KINDS = [
        self::RECEBIDA => 'Recebida',
        self::ENVIADA => 'Enviada',
        self::ENCERRAMENTO => 'Encerramento',
    ];

    public const OUTCOMES = [
        self::AGUARDANDO => 'Aguardando a Poli',
        self::ATENDENTE => 'Com atendente',
        self::FILA => 'Na fila do time',
        self::DEVOLVIDO => 'Voltou para O Lara',
        self::FALHOU => 'A Poli recusou',
    ];

    protected $fillable = [
        'occurred_at',
        'contact_uuid',
        'contact_phone',
        'contact_name',
        'attendance_uuid',
        'kind',
        'reason',
        'detail',
        'actor',
        'target',
        'outcome',
        'outcome_detail',
        'poli_message_id',
        'poli_text',
        'flow_slug',
        'step_key',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function reasonLabel(): string
    {
        return self::REASONS[$this->kind][$this->reason] ?? $this->reason;
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }

    public function outcomeLabel(): ?string
    {
        return $this->outcome === null ? null : (self::OUTCOMES[$this->outcome] ?? $this->outcome);
    }
}
