<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Outer extends Model
{
    use HasFactory;

    protected $conneciton = 'mysql';

    protected $fillable = [
        'name',
        'cpf',     
        'telephone',
        'image',
        'company_id'
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function accessRules()
    {
        return $this->hasOne(AccessRule::class);
    }
}
