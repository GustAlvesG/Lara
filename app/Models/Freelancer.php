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
        'rg',
        'email',
        'nacionality',
        'civil_status',
        'address',
        'telephone',
    ];

    public function freelancerServices()
    {
        return $this->hasMany(FreelancerService::class);
    }

}
