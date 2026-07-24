<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Company\CompanyAccessLog;

class AppDriver extends Model
{
    use HasFactory;

    protected $fillable = [
        'plate',
        'name',
    ];

    public function accesses()
    {
        return $this->hasMany(CompanyAccessLog::class);
    }
}
