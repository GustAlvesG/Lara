<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Models\Information;

class DataInfo extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $conneciton = 'mysql';

    const UPDATED_AT = null;

    protected $fillable = [
        'name',
        'description',
        'fee',
        'image',
        'category',
        'responsible',
        'responsible_contact',
        'name_price',
        'price_associated',
        'price_not_associated',
        'slots',
        'day_hour',
        'location',
        'status',
        'information_id',
        'created_by',
        'before_data',

    ];

    //Relacionamento de um para um com usuario
    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }


    public function information()
    {
        return $this->belongsTo(Information::class);
    }
}
