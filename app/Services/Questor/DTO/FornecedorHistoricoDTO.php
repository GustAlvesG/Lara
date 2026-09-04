<?php

namespace App\Services\Questor\DTO;

use Illuminate\Support\Carbon;

/**
 * Um fornecedor que já forneceu determinado material (queries 5 e 5.c).
 *
 * É a resposta para "a quem eu peço cotação disto?" — e a base da sugestão de
 * colunas do mapa. Note que a sugestão é só sugestão: quem escolhe as colunas é
 * o comprador, senão um item comprado de vinte fornecedores nasceria com vinte
 * colunas.
 *
 * `item` vem preenchido na consulta em lote (5.c), que traz os fornecedores de
 * todos os itens da SC de uma vez; fica nulo na consulta de um material só (5).
 */
final readonly class FornecedorHistoricoDTO
{
    public function __construct(
        public ?int $item,
        public ?int $material,
        public int $codigo,
        public string $nome,
        public ?string $fantasia,
        public ?string $cnpj,
        public ?string $telefone,
        public ?string $email,
        public ?string $cidade,
        public ?string $uf,
        public bool $ativo,
        public int $qtdCompras,
        public ?Carbon $ultimaCompra,
        public ?float $valorUnitarioUltimo,
        public ?float $valorUnitarioMedio,
        public ?float $valorUnitarioMinimo,
    ) {
    }

    public static function deLinha(object $l): self
    {
        return new self(
            item: isset($l->CD_ITEM) ? (int) $l->CD_ITEM : null,
            material: isset($l->CD_MATERIAL) && $l->CD_MATERIAL !== null ? (int) $l->CD_MATERIAL : null,
            codigo: (int) $l->CD_FORNECEDOR,
            nome: trim((string) ($l->DS_ENTIDADE ?? '')) ?: 'FORNECEDOR ' . $l->CD_FORNECEDOR,
            fantasia: self::texto($l->DS_FANTASIA ?? null),
            cnpj: self::texto($l->NR_CPFCNPJ ?? null),
            telefone: self::texto($l->NR_TELEFONE ?? null),
            // DS_EMAIL_ORD_COMPRA é o e-mail que o Questor usa para mandar
            // ordem de compra; quando existe, é o endereço certo para cotar.
            email: self::texto($l->DS_EMAIL_ORD_COMPRA ?? null) ?? self::texto($l->DS_EMAIL ?? null),
            cidade: self::texto($l->DS_CIDADE ?? null),
            uf: self::texto($l->DS_UF ?? null),
            ativo: (bool) ($l->X_ATIVO ?? 1),
            qtdCompras: (int) ($l->QTD_COMPRAS ?? 0),
            ultimaCompra: filled($l->DT_ULTIMA ?? null) ? Carbon::parse($l->DT_ULTIMA) : null,
            valorUnitarioUltimo: self::valor($l->VL_UNIT_ULTIMO ?? null),
            valorUnitarioMedio: self::valor($l->VL_UNIT_MEDIO ?? null),
            valorUnitarioMinimo: self::valor($l->VL_UNIT_MIN ?? null),
        );
    }

    /**
     * Nome como o comprador o chama.
     */
    public function nomeCurto(): string
    {
        return $this->fantasia ?: $this->nome;
    }

    /**
     * Os dados que viram uma coluna do mapa, se o comprador escolher este
     * fornecedor. Fica aqui para o serviço de importação não remontar o mapa de
     * campos toda vez.
     *
     * @return array<string, mixed>
     */
    public function paraColuna(): array
    {
        return [
            'questor_cd_entidade' => $this->codigo,
            'nome' => mb_substr($this->nomeCurto(), 0, 150),
            'cnpj' => $this->cnpj ? mb_substr($this->cnpj, 0, 20) : null,
            'email' => $this->email ? mb_substr($this->email, 0, 150) : null,
            'telefone' => $this->telefone ? mb_substr($this->telefone, 0, 20) : null,
        ];
    }

    private static function texto(?string $v): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : $v;
    }

    private static function valor(mixed $v): ?float
    {
        return $v === null ? null : (float) $v;
    }
}
