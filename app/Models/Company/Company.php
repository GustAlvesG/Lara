<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Company\CompanyWorker;
use App\Models\Company\CompanyAccessRule;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    /** @use HasFactory<\Database\Factories\CompanyFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'address',
        'email',
        'telephone',
        'description',
        'image'
    ];

    protected $dates = [
        'deleted_at'
    ];

    public function workers()
    {
        return $this->hasMany(CompanyWorker::class);
    }

    public function rules()
    {
        return $this->hasMany(CompanyAccessRule::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (Company $company) {
            $company->workers()->each(fn($w) => $w->delete());
            $company->rules()->each(fn($r) => $r->delete());
        });
    }
}
