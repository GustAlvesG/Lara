<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AvisoView extends Model
{
    public $timestamps = false;

    protected $table = 'aviso_views';

    protected $fillable = ['aviso_id', 'user_id', 'viewed_at'];

    protected $casts = ['viewed_at' => 'datetime'];

    public function aviso()
    {
        return $this->belongsTo(Aviso::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
