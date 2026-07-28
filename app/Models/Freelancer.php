<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Freelancer extends Model
{
    /** @use HasFactory<\Database\Factories\FreelancerFactory> */
    use HasFactory;

    protected $table = 'freelancers';

    /**
     * Campos que precisam estar preenchidos para o freelancer poder ter um
     * contrato gerado. `name` e `cpf` são sempre exigidos no cadastro, então
     * não entram aqui; `email` é opcional e não sai no contrato.
     *
     * @var array<int, string>
     */
    public const CONTRACT_REQUIRED_FIELDS = [
        'rg',
        'nacionality',
        'civil_status',
        'address',
        'telephone',
    ];

    /**
     * Rótulos dos campos obrigatórios do contrato, para exibição ao usuário.
     *
     * @var array<string, string>
     */
    public const CONTRACT_FIELD_LABELS = [
        'rg' => 'RG',
        'nacionality' => 'nacionalidade',
        'civil_status' => 'estado civil',
        'address' => 'endereço',
        'telephone' => 'telefone',
    ];

    protected $fillable = [
        'name',
        'cpf',
        'pix_key',
        'rg',
        'email',
        'nacionality',
        'civil_status',
        'address',
        'telephone',
        'created_by',
        'updated_by',
    ];

    /**
     * Quando a chave PIX não é informada, ela é igual ao CPF. A regra fica no
     * model para valer em qualquer caminho de gravação (painel e API).
     */
    protected static function booted(): void
    {
        static::saving(function (Freelancer $freelancer) {
            if (blank($freelancer->pix_key)) {
                $freelancer->pix_key = $freelancer->cpf;
            }
        });
    }

    /* ---------------------------------------------------------------------
     | Completude do cadastro (pré-requisito para gerar contrato)
     |---------------------------------------------------------------------*/

    /**
     * Campos obrigatórios do contrato que ainda estão em branco.
     *
     * @return array<int, string>
     */
    public function missingContractFields(): array
    {
        return array_values(array_filter(
            self::CONTRACT_REQUIRED_FIELDS,
            fn(string $field) => blank($this->{$field})
        ));
    }

    /**
     * Rótulos (em português) dos campos pendentes, para mensagens ao usuário.
     *
     * @return array<int, string>
     */
    public function missingContractFieldLabels(): array
    {
        return array_map(
            fn(string $field) => self::CONTRACT_FIELD_LABELS[$field] ?? $field,
            $this->missingContractFields()
        );
    }

    /**
     * O cadastro tem todos os dados necessários para gerar um contrato?
     */
    public function hasCompleteContractData(): bool
    {
        return $this->missingContractFields() === [];
    }

    public function freelancerServices()
    {
        return $this->hasMany(FreelancerService::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
