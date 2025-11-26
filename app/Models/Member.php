<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Member extends Model
{

    use HasFactory;
    //Choose connection
    protected $connection = 'mysql';

    //Choose table
    protected $table = 'members';

    //Choose primary key
    protected $fillable = [
        'Id',
        'title',
        'cpf',
        'birth_date',
        'Barcode',
        'Name',
        'Titular',
        'telephone',
        'Email',
        'Password',
    ];

    protected $hidden = [
        'password',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    

}
