<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Company;
use App\Models\CompanyWorker;

class CompanyAccessRule extends Model
{
    /** @use HasFactory<\Database\Factories\CompanyAccessRulesFactory> */
    use HasFactory;

    protected $table = 'company_access_rules';

    protected $fillable = [
        'company_id',
        'company_worker_id',
        'type',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function worker()
    {
        return $this->belongsTo(CompanyWorker::class, 'company_worker_id');
    }
}
