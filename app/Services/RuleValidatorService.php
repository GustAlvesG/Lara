<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RuleValidatorService
{
    // Rule validation related service methods would go here
    public function validate($rule, $data)
    {
        $response = true;
        $valid_period = null;
        $valid_weekday = null;
        $valid_time = null;

        $start_date = $rule->start_date ?? null;
        $end_date = $rule->end_date ?? null;
        $start_time = $rule->start_time ?? null;
        $end_time = $rule->end_time ?? null;


        if ($start_date || $end_date) {
            $valid_period = $this->isBetweenOrGreaterDates($data['current_date'], $start_date, $end_date);
            if (!$valid_period) {
                return false;
            }
        }


        if ($rule->weekdays && count($rule->weekdays) > 0) {
            $valid_weekday = $this->isOnWeekday($data['current_date'], $rule->weekdays->pluck('id')->toArray());
            if (!$valid_weekday) {

                return false;
            }
        }

        if ($start_time && $end_time) {
            $valid_time = $this->isBetweenTimes($data['current_time'], $start_time, $end_time);
            if (!$valid_time) {
                return false;
            }
        }

        return $response;
    }

    private function isBetweenTimes($currentTime, $startTime, $endTime)
    {
        return ($currentTime >= $startTime && $currentTime <= $endTime);
    }

    private function isOnWeekday($currentDay, $weekdays)
    {
        // date('N') = 1 (Mon) … 7 (Sun). DB stores: 1=Sun, 2=Mon … 7=Sat.
        // Formula: dbId = (phpN % 7) + 1  →  Mon(1)→2, Tue(2)→3 … Sun(7)→1
        $phpN  = (int) date('N', strtotime($currentDay));
        $dbId  = ($phpN % 7) + 1;
        return in_array($dbId, $weekdays);
    }

    private function isBetweenDates($currentDate, $startDate, $endDate)
    {
        return ($currentDate >= $startDate && $currentDate <= $endDate);
    }

    public function isBetweenOrGreaterDates($currentDate, $startDate = null, $endDate = null)
    {
        if ($endDate) {
            return ($currentDate >= $startDate && $currentDate <= $endDate);
        } else {
            return ($currentDate >= $startDate);
        }
    }
}