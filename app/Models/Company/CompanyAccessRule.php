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

    /**
     * Indica se a regra está dentro do período de vigência (data de hoje entre
     * start_date e end_date). Não considera dia da semana ou horário — apenas o
     * intervalo de datas. Regras sem end_date são vigentes indefinidamente.
     */
    public function isWithinValidityPeriod(?string $today = null): bool
    {
        $today = $today ?? now()->toDateString();

        if ($this->start_date && $today < \Illuminate\Support\Carbon::parse($this->start_date)->toDateString()) {
            return false;
        }

        if ($this->end_date && $today > \Illuminate\Support\Carbon::parse($this->end_date)->toDateString()) {
            return false;
        }

        return true;
    }
}
