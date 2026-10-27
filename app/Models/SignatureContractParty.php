<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Contratada ou fiador já usados num contrato, guardados para preencher o
 * próximo. Um por documento (CNPJ/CPF); salvar o contrato atualiza o
 * cadastro. Não vem do Questor, de propósito (decisão do usuário).
 */
class SignatureContractParty extends Model
{
    protected $fillable = [
        'kind',
        'document',
        'name',
        'data',
        'updated_by_name',
    ];

    protected $casts = [
        'data' => 'array',
    ];
}
