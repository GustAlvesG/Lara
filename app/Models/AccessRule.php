<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccessRule extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $conneciton = 'mysql';

    protected $fillable = [
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'weekdays',
        'status',
        'type',
        'company_id',
        'outer_id'
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function outer()
    {
        return $this->belongsTo(Outer::class);
    }
}
