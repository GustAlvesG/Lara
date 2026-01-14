<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model {
    protected $fillable = ['contact_id', 'user_id', 'status', 'last_message_at'];
    
    public function messages() {
        return $this->hasMany(Message::class);
    }
}