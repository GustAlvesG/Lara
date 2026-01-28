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

        if (isset($rule->start_date) && isset($rule->end_date)) {
            $currentDate = date('Y-m-d');
            if ($this->isBetweenDates($currentDate, $rule->start_date, $rule->end_date)) {
                $valid_period = $rule->type !== 'exclude';
            }
        } else if (isset($rule->start_date)) {
            $currentDate = date('Y-m-d');
            if ($currentDate > $rule->start_date) {
                $valid_period = $rule->type !== 'exclude';
            }
        }

        if (isset($rule->weekdays) && is_array($rule->weekdays) && count($rule->weekdays) > 0) {
            $currentDay = $data['date'] ?? date('Y-m-d');
            if ($this->isOnWeekday($currentDay, $rule->weekdays)) {
                $valid_weekday = $rule->type !== 'exclude';
            }
        }
    

        if (isset($rule->start_time) && isset($rule->end_time)) {
            $currentTime = $data['time'] ?? date('H:i:s');
            if ($this->isBetweenTimes($currentTime, $rule->start_time, $rule->end_time)) {
                $valid_time = $rule->type !== 'exclude';
            }
        }



    }

    private function isBetweenTimes($currentTime, $startTime, $endTime)
    {
        return ($currentTime >= $startTime && $currentTime <= $endTime);
    }

    private function isOnWeekday($currentDay, $weekdays)
    {
        //Get current weekday by date
        $current_weekday = date('N', strtotime($currentDay)); // 1 (for Sunday) through 7 (for Saturday)
        return in_array($current_weekday, $weekdays);
    }

    private function isBetweenDates($currentDate, $startDate, $endDate)
    {
        return ($currentDate >= $startDate && $currentDate <= $endDate);
    }

    public function isBetweenOrGreaterDates($currentDate, $startDate, $endDate = null)
    {
        if ($endDate) {
            return ($currentDate >= $startDate && $currentDate <= $endDate);
        } else {
            return ($currentDate >= $startDate);
        }
    }
}