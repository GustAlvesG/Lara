<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma entrada da trilha do mapa.
 *
 * Só escreve, nunca atualiza — por isso `UPDATED_AT` é nulo. Registrar é
 * responsabilidade de quem faz a ação, via {@see registrar()}, para o formato
 * do payload ficar num lugar só.
 *
 * Um mapa de cotação decide para onde vai dinheiro, e a pergunta que aparece
 * depois é sempre "quem mudou este preço, e quando?". A planilha em Excel não
 * responde isso; esta tabela é boa parte da razão de o módulo existir.
 */
class CotacaoMapaLog extends Model
{
    public const ACAO_CRIACAO = 'criacao';
    public const ACAO_IMPORTACAO = 'importacao';
    public const ACAO_ATUALIZOU_HISTORICO = 'atualizou_historico';
    public const ACAO_PRECO = 'preco';
    public const ACAO_FORNECEDOR = 'fornecedor';
    public const ACAO_ITEM = 'item';
    public const ACAO_VENCEDOR = 'vencedor';
    public const ACAO_STATUS = 'status';
    public const ACAO_EXPORTACAO = 'exportacao';

    public const UPDATED_AT = null;

    protected $fillable = [
        'cotacao_mapa_id',
        'user_id',
        'user_nome',
        'acao',
        'payload',
    ];

    protected $casts = [
        'cotacao_mapa_id' => 'integer',
        'user_id' => 'integer',
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<CotacaoMapa, CotacaoMapaLog>
     */
    public function mapa(): BelongsTo
    {
        return $this->belongsTo(CotacaoMapa::class, 'cotacao_mapa_id');
    }

    /**
     * Grava uma entrada. O autor entra como id + nome: o nome é retrato, para a
     * trilha continuar legível se o usuário for renomeado ou removido.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function registrar(
        CotacaoMapa $mapa,
        string $acao,
        array $payload = [],
        ?User $autor = null
    ): self {
        return self::create([
            'cotacao_mapa_id' => $mapa->id,
            'user_id' => $autor?->id,
            'user_nome' => $autor?->name,
            'acao' => $acao,
            'payload' => $payload !== [] ? $payload : null,
        ]);
    }

    /**
     * Rótulo da ação para a tela de histórico.
     */
    public function acaoLabel(): string
    {
        return match ($this->acao) {
            self::ACAO_CRIACAO => 'Mapa criado',
            self::ACAO_IMPORTACAO => 'Itens importados da SC',
            self::ACAO_ATUALIZOU_HISTORICO => 'Histórico de compra atualizado',
            self::ACAO_PRECO => 'Preço alterado',
            self::ACAO_FORNECEDOR => 'Coluna de fornecedor',
            self::ACAO_ITEM => 'Item do mapa',
            self::ACAO_VENCEDOR => 'Vencedor definido',
            self::ACAO_STATUS => 'Situação do mapa',
            self::ACAO_EXPORTACAO => 'Exportado em XLSX',
            default => $this->acao,
        };
    }
}
