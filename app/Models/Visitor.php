<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Visitor extends Model
{
    use HasFactory;
    //Choose connection
    protected $connection = 'mc_sqlsrv';

    //Choose table
    protected $table = 'dbo.Visitors';

    protected $fillable = [
        'Id',
        'Name',
        'MobilePhone',
    ];
}
