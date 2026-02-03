<?php

namespace App\Services;

use App\Models\SchedulePayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSchedulePaymentRequest;
use App\Http\Requests\UpdateSchedulePaymentRequest;
use App\Models\Schedule;
use App\Services\ScheduleRulesService;
//use DB
use Illuminate\Support\Facades\DB;

class SchedulePaymentService
{
    public function __construct()
    {
        $this->scheduleRulesService = new ScheduleRulesService();
    }

    public function createSchedulePayment(StoreSchedulePaymentRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $schedulePayment = SchedulePayment::create($request->validated());

            // Additional logic can be added here if needed

            return $schedulePayment;
        });
    }

}