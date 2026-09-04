<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Uma decisão gravada no Questor — e o único lugar onde "quem aprovou" existe.
 *
 * O ERP guarda um autorizador só, que é sempre o usuário técnico da Lara. Esta
 * linha é o que liga aquele carimbo à pessoa que clicou. Nada aqui é
 * sobrescrito: uma segunda tentativa na mesma ordem é uma segunda linha.
 *
 * `rows_affected = 0` é o caso que mais importa ler: a gravação foi enviada e
 * não pegou nada, porque a ordem já tinha saído da fila.
 */
class QuestorOrderDecision extends Model
{
    public const ACTION_APPROVE = 'aprovacao';
    public const ACTION_REJECT = 'reprovacao';

    protected $fillable = [
        'cd_ordem_compra',
        'cd_filial',
        'action',
        'questor_user',
        'decided_by',
        'decided_by_name',
        'motivo',
        'vl_total',
        'rows_affected',
        'executed',
    ];

    protected $casts = [
        'cd_ordem_compra' => 'integer',
        'cd_filial' => 'integer',
        'questor_user' => 'integer',
        'decided_by' => 'integer',
        'vl_total' => 'decimal:2',
        'rows_affected' => 'integer',
        'executed' => 'boolean',
    ];

    /**
     * A gravação chegou ao ERP e mudou a ordem? `false` aqui é uma tentativa
     * que não pegou linha nenhuma — registrada de propósito.
     */
    public function tookEffect(): bool
    {
        return $this->executed && $this->rows_affected > 0;
    }
}
