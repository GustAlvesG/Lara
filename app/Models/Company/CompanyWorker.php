<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Company\Company;
use App\Models\Company\CompanyAccessLog;
use App\Models\Company\CompanyAccessRule;
use App\Models\User;

class CompanyWorker extends Model
{
    /** @use HasFactory<\Database\Factories\CompanyWorkerFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'email',
        'position',
        'document',
        'telephone',
        'image',
        'created_by_user',
        'updated_by_user',
    ];

    protected $dates = [
        'deleted_at'
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function rules()
    {
        return $this->hasMany(CompanyAccessRule::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by_user');
    }

    public function latestAccessLog()
    {
        return $this->hasOne(CompanyAccessLog::class)->latestOfMany();
    }
}
