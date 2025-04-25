<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Models\DataInfo;

class Information extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $conneciton = 'mysql';

    protected $fillable = [
        'created_by',
        'privacy'
    ];

    //Foreign key
    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    //Relacionamento de um para muitos com data_info
    public function data_info()
    {
        return $this->hasMany(DataInfo::class, 'created_by');
    }
}
