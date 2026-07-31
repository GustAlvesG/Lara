<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AvisoAcknowledgement extends Model
{
    public $timestamps = false;

    protected $table = 'aviso_acknowledgements';

    protected $fillable = ['aviso_id', 'user_id', 'acknowledged_at', 'ip_address'];

    protected $casts = ['acknowledged_at' => 'datetime'];

    public function aviso()
    {
        return $this->belongsTo(Aviso::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
