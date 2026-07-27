<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Freelancer extends Model
{
    /** @use HasFactory<\Database\Factories\FreelancerFactory> */
    use HasFactory;

    protected $table = 'freelancers';

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
