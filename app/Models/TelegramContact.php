<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramContact extends Model
{
    protected $table = 'contact_telegram';

    protected $fillable = [
        'name',
        'chat_id',
        'phone',
    ];
}
