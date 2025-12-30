<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Status extends Model
{
    use HasFactory;
    public $timestamps = false;

    #Table name
    protected $table = 'status';

    protected $fillable = [
        'name',
        'portuguese',
    ];

    public function schedule()
    {
        return $this->hasMany(Schedule::class);
    }

    public function users()
    {
        return $this->hasMany(User::class, 'status_id');
    }
}
