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
    ];

    public function freelancerServices()
    {
        return $this->hasMany(FreelancerService::class);
    }
}
