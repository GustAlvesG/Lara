<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;

class CompanyAccessLog extends Model
{
    protected $fillable = [
        'company_id',
        'company_worker_id',
        'app_driver_id',
        'target',
        'obs',
        'allowed',
        'reason',
    ];

    protected $casts = [
        'allowed' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function worker()
    {
        return $this->belongsTo(CompanyWorker::class, 'company_worker_id');
    }

    public function appDriver()
    {
        return $this->belongsTo(\App\Models\AppDriver::class, 'app_driver_id');
    }
}
