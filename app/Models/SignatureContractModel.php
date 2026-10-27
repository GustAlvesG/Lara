<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo de contrato completo do setor ("Prestação de serviços", "Locação"):
 * título, termos das partes, variáveis (sem valor), considerandos e cláusulas.
 * Um contrato novo parte de uma cópia dele. Ver ContractService::modelContent.
 */
class SignatureContractModel extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'sector_id',
        'sector_name',
        'name',
        'content',
        'created_by',
        'created_by_name',
    ];

    protected $casts = [
        'sector_id' => 'integer',
        'content' => 'array',
        'created_by' => 'integer',
    ];
}
