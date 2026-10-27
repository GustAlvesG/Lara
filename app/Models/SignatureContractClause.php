<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cláusula salva na biblioteca do setor, para reaproveitar.
 *
 * Inserida num contrato, ela é COPIADA: mudar a biblioteca depois não mexe em
 * contrato nenhum (o mesmo princípio dos modelos de documento).
 */
class SignatureContractClause extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'sector_id',
        'sector_name',
        'title',
        'category',
        'items',
        'created_by',
        'created_by_name',
        'updated_by_name',
    ];

    protected $casts = [
        'sector_id' => 'integer',
        'items' => 'array',
        'created_by' => 'integer',
    ];
}
