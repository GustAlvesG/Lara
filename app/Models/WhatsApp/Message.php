<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model {
    protected $fillable = ['conversation_id', 'wam_id', 'type', 'direction', 'body', 'status'];
    
    public function media() {
        return $this->hasOne(MediaAttachment::class);
    }
}
