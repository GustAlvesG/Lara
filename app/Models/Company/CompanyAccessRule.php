<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Weekday;

class CompanyAccessRule extends Model
{
    /** @use HasFactory<\Database\Factories\CompanyAccessRulesFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'company_access_rules';

    protected $fillable = [
        'company_id',
        'company_worker_id',
        'type',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'description'
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function worker()
    {
        return $this->belongsTo(CompanyWorker::class, 'company_worker_id');
    }

    public function weekdays()
    {
        return $this->belongsToMany(Weekday::class, 'week_days_company_access_rule', 'company_access_rule_id', 'weekday_id')
            ->withPivot('id')
            ->withTimestamps();
    }
}
