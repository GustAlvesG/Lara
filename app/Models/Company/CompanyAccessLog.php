<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;

class CompanyAccessLog extends Model
{
    protected $fillable = [
        'company_id',
        'company_worker_id',
        'app_driver_id',
        'uber_access_request_id',
        'freelancer_id',
        'freelancer_service_id',
        'target',
        'obs',
        'screenshot_url',
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

    public function uberRequest()
    {
        return $this->belongsTo(\App\Models\UberAccessRequest::class, 'uber_access_request_id');
    }

    /** Freelancer consultado por CPF na portaria (permitido ou negado). */
    public function freelancer()
    {
        return $this->belongsTo(\App\Models\Freelancer::class, 'freelancer_id');
    }

    /** O contrato que liberou a entrada — null quando o acesso foi negado. */
    public function freelancerService()
    {
        return $this->belongsTo(\App\Models\FreelancerService::class, 'freelancer_service_id');
    }

    /**
     * Acesso originado de um carro de aplicativo: seja pelo fluxo do Uber
     * (uber_access_request_id preenchido / reason "uber_*") ou pelo registro
     * manual de motorista de app (app_driver_id preenchido).
     */
    public function scopeAppCars($query)
    {
        return $query->where(function ($q) {
            $q->whereNotNull('app_driver_id')
                ->orWhereNotNull('uber_access_request_id')
                ->orWhere('reason', 'like', 'uber%');
        });
    }

    /**
     * Somente os acessos vindos do fluxo de Uber (exclui o registro manual de
     * motorista de app).
     */
    public function scopeUber($query)
    {
        return $query->where(function ($q) {
            $q->whereNotNull('uber_access_request_id')
                ->orWhere('reason', 'like', 'uber%');
        });
    }
}
