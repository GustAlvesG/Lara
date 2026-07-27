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
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'price' => 'decimal:2',
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
