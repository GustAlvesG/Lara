<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FunctionFreelancer extends Model
{
    /** @use HasFactory<\Database\Factories\FunctionFreelancerFactory> */
    use HasFactory;

    protected $table = 'function_freelancers';

    protected $fillable = [
        'name',
        'description',
        'price',
        // Quem exerce esta função pode receber aditivo de comissão de venda.
        // É uma permissão por função, e não o nome "Garçom" no código: nomes
        // mudam, e a regra precisa acompanhar sem deploy.
        'allows_sales_commission',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'allows_sales_commission' => 'boolean',
    ];

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
