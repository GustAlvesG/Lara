<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UberAccessRequestMessage extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'uber_access_request_id',
        'poli_message_id',
        'raw_payload',
    ];

    protected $casts = [
        'raw_payload' => 'array',
    ];

    public function uberAccessRequest()
    {
        return $this->belongsTo(UberAccessRequest::class);
    }
}
