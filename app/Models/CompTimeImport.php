<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompTimeImport extends Model
{
    protected $fillable = [
        'uuid',
        'status',
        'phase',
        'temp_file_path',
        'result_data',
        'error_message',
    ];

    protected $casts = [
        'result_data' => 'array',
    ];
}
