<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Weekday extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $fillable = [
        'name',
        'short_name',
        'name_pt',
        'short_name_pt',
    ];

    public function scheduleRules()
    {
        return $this->belongsToMany(ScheduleRules::class, 'week_days_schedule_rule');
    }

    public function companyAccessRules()
    {
        return $this->belongsToMany(CompanyAccessRules::class, 'week_days_company_access_rule');
    }
}
