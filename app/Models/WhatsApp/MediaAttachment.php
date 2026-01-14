<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaAttachment extends Model {
    protected $fillable = ['message_id', 'whatsapp_media_id', 'file_type', 'mime_type', 'file_path', 'file_name'];
}