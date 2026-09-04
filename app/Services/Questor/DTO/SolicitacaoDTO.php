<?php

namespace App\Services\Questor\DTO;

use Illuminate\Support\Carbon;

/**
 * O cabeçalho de uma Solicitação de Compra do Questor (query 1).
 *
 * `readonly` não é enfeite: é a forma de o resto da aplicação não conseguir
 * "corrigir" um dado do ERP em memória e depois achar que corrigiu no ERP.
 * Objeto de leitura, sem setters, sem `save()`.
 */
final readonly class SolicitacaoDTO
{
    public function __construct(
        public int $codigo,
        public ?int $empresa,
        public ?int $filial,
        public ?string $filialNome,
        public ?string $solicitante,
        public ?int $departamentoCodigo,
        public ?string $departamento,
        public ?Carbon $dataCadastro,
        public ?Carbon $dataFinalizacao,
        public ?string $observacoes,
        public ?int $statusCodigo,
        public ?string $status,
        public ?string $obra,
        public ?string $usuarioCadastro,
        public int $qtdItens,
    ) {
    }

    /**
     * Monta o DTO a partir da linha crua da query 1.
     */
    public static function deLinha(object $l): self
    {
        return new self(
            codigo: (int) $l->CD_SOLICITACAO,
            empresa: isset($l->CD_EMPRESA) ? (int) $l->CD_EMPRESA : null,
            filial: isset($l->CD_FILIAL) ? (int) $l->CD_FILIAL : null,
            filialNome: self::texto($l->DS_FILIAL ?? null),
            solicitante: self::texto($l->DS_SOLICITANTE ?? null),
            departamentoCodigo: isset($l->CD_DEPARTAMENTO) ? (int) $l->CD_DEPARTAMENTO : null,
            departamento: self::texto($l->DS_DEPARTAMENTO ?? null),
            dataCadastro: self::data($l->DT_CADASTRO ?? null),
            dataFinalizacao: self::data($l->DT_FINALIZACAO ?? null),
            observacoes: self::texto($l->DS_OBS ?? null),
            statusCodigo: isset($l->CD_STATUS) ? (int) $l->CD_STATUS : null,
            status: self::texto($l->DS_STATUS ?? null),
            obra: self::texto($l->DS_OBRA ?? null),
            usuarioCadastro: self::texto($l->USUARIO_CADASTRO ?? null),
            qtdItens: (int) ($l->QTD_ITENS ?? 0),
        );
    }

    /**
     * Título que a Lara sugere para o mapa quando o comprador não digita um.
     *
     * Usa a observação da SC, que na prática é onde o solicitante escreve o
     * "para quê" da compra — exatamente o que vai em C1 do XLSX modelo
     * ("MATERIAL PARA PINTURA DA CERCA DO PARQUINHO..."). Sem observação, sobra
     * o número, que ao menos identifica.
     */
    public function tituloSugerido(): string
    {
        $obs = trim((string) $this->observacoes);

        if ($obs !== '') {
            return mb_strtoupper(mb_substr($obs, 0, 255));
        }

        return 'SOLICITAÇÃO DE COMPRA ' . $this->codigo;
    }

    private static function texto(?string $v): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : $v;
    }

    private static function data(mixed $v): ?Carbon
    {
        return filled($v) ? Carbon::parse($v) : null;
    }
}
