<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model {
    protected $fillable = ['wa_id', 'name', 'profile_pic_url'];
    
    public function conversations() {
        return $this->hasMany(Conversation::class);
    }
    
    // Pega a conversa aberta atual, se houver
    public function activeConversation() {
        return $this->hasOne(Conversation::class)->where('status', 'open')->latest();
    }
}