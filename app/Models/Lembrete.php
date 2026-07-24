<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lembrete extends Model
{
    protected $table = 'aviso_lembretes';

    protected $fillable = ['aviso_id', 'remind_at', 'sent'];

    protected $casts = [
        'remind_at' => 'datetime',
        'sent' => 'boolean',
    ];

    public function aviso()
    {
        return $this->belongsTo(Aviso::class);
    }
}
