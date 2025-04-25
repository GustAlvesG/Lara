<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $conneciton = 'mysql';

    protected $fillable = [
        'name',
        'email',
        'image',       
        'telephone',
        'address',
        'description',
        'status'

    ];

    public function accessRules()
    {
        return $this->hasOne(AccessRule::class);
    }

    public function outers()
    {
        return $this->hasMany(Outer::class);
    }

}
