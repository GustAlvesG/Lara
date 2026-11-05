<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Prestador (contratada ou fiador), guardado para preencher o próximo
 * contrato. Um por documento (CNPJ/CPF); salvar um contrato atualiza o
 * cadastro. Não vem do Questor, de propósito (decisão do usuário).
 *
 * Desde 05/11/2026 tem tela própria (Contratos → Prestadores): dá para
 * cadastrar sem contrato. Não se exclui, só se inativa (`active`): o
 * inativo some da busca do editor; os contratos que o usam não mudam,
 * porque os dados da parte ficam no próprio contrato.
 */
class SignatureContractParty extends Model
{
    /** Os campos de `data` (o resto é coluna). */
    public const DATA_FIELDS = ['gender', 'address', 'details', 'rep_name', 'rep_cpf', 'email', 'phone'];

    protected $fillable = [
        'kind',
        'document',
        'name',
        'data',
        'active',
        'updated_by_name',
        'created_by_name',
    ];

    protected $attributes = [
        'active' => true,
    ];

    protected $casts = [
        'data' => 'array',
        'active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /** Busca por nome, representante ou documento (com ou sem máscara). */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $digitos = strtoupper((string) preg_replace('/[^0-9A-Za-z]/', '', $term));

        return $query->where(fn(Builder $q) => $q->where('name', 'like', '%' . $term . '%')
            ->orWhere('data->rep_name', 'like', '%' . $term . '%')
            ->when(strlen($digitos) >= 3, fn(Builder $w) => $w->orWhere('document', 'like', $digitos . '%')));
    }

    public function field(string $key): string
    {
        return (string) (($this->data ?? [])[$key] ?? '');
    }

    /** CNPJ/CPF com a máscara, para a tela. */
    public function formattedDocument(): string
    {
        $d = (string) $this->document;

        if ($this->kind === 'pf' && strlen($d) === 11) {
            return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9);
        }

        if (strlen($d) === 14) {
            return substr($d, 0, 2) . '.' . substr($d, 2, 3) . '.' . substr($d, 5, 3) . '/' . substr($d, 8, 4) . '-' . substr($d, 12);
        }

        return $d;
    }

    /** Para preencher a parte no editor do contrato (mesma forma do `content`). */
    public function toPartyArray(): array
    {
        return ['kind' => $this->kind, 'name' => $this->name, 'document' => $this->document]
            + array_intersect_key($this->data ?? [], array_flip(self::DATA_FIELDS));
    }
}
