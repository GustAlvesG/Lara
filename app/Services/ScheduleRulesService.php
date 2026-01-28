<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\ScheduleRules;
use App\Services\RuleValidationService as RuleCheckService;


class ScheduleRulesService
{

    // Service methods would go here

    public function store(Request $request){

        $validated = $request->all();


        foreach ($validated['places'] as $key => $place) {
            $places[] = $place;
        }
        foreach($validated['days'] as $key => $day){
            $days[] = $day;
        }

        
        
        
        
        DB::beginTransaction();
        try {
            // Logic to store a ScheduleRule
            $rule = ScheduleRules::create($validated);

            if(isset($places)){
                $rule->places()->attach($places);
            }

            if(isset($days)){
                $rule->weekdays()->attach($days);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()->back()->with('success', 'Regra de Horário criada com sucesso!');
    }

    public function getFilteredRulesByPlaceGroup($placeGroup){


        // Logic to get filtered ScheduleRules.
        // ScheduleRule has places relationship
        // Place has place_group_id field


        $rules = ScheduleRules::whereHas('places', function($query) use ($placeGroup) {
            $query->where('place_group_id', $placeGroup->id);
        })->get();
       

        return $rules;
    }

    public function getTimeOptions($place_id, $date){
        $weekday = Carbon::parse($date)->dayOfWeek + 1; // Carbon's dayOfWeek starts from 0 (Sunday) to 6 (Saturday)

        $rules = $this->getRules($place_id)->filter(function ($rule) use ($weekday) {
            return $rule->weekdays->contains('id', $weekday);
        });

        $place_group = $rules->first()->places->first()->group;
        $defaultTimes = $this->getTimes($place_group->start_time, $place_group->end_time, $place_group->duration);

        
        $rules_include = $rules->where('type', 'include');

        $timesToInclude = [];


        foreach ($rules_include as $rule) {
            if ($rule->start_date || $rule->end_date) {
                // Check if the date is within the rule's date range
                $currentDate = Carbon::parse($date)->format('Y-m-d');
                if (!$this->isBetweenOrGreaterDates($currentDate, $rule->start_date, $rule->end_date)) {
                    continue; // Skip this rule if the date is not in range
                }
            }
            if ($rule->weekdays && !$rule->weekdays->contains('id', $weekday)) {
                continue; // Skip this rule if the weekday does not match
            }
            $timesToInclude[] =  $this->getTimes($rule->start_time ?? '00:00:00', $rule->end_time ?? '23:59:59', $place_group->duration);
        }

        $timesToInclude = array_merge(...$timesToInclude);
            
        $timeOptions = array_unique(array_merge($defaultTimes, $timesToInclude), SORT_REGULAR);

        $rules_exclude = $rules->where('type', 'exclude');
        foreach ($rules_exclude as $rule) {
            if ($rule->start_date || $rule->end_date) {
                // Check if the date is within the rule's date range
                $currentDate = Carbon::parse($date)->format('Y-m-d');
                if (!$this->isBetweenOrGreaterDates($currentDate, $rule->start_date, $rule->end_date)) {
                    continue; // Skip this rule if the date is not in range
                }
            }
            //Check weekdays
            if ($rule->weekdays && !$rule->weekdays->contains('id', $weekday)) {
                continue; // Skip this rule if the weekday does not match
            }
            $startExclude = Carbon::parse($rule->start_time ?? '00:00:00');
            $endExclude = Carbon::parse($rule->end_time ?? '23:59:59');
            $timesToExclude = $this->excludeTimes($timeOptions, $startExclude, $endExclude, $rule); 

            foreach($timesToExclude as $toExclude){
                $key = array_search([
                    'start_time' => $toExclude['start_time'],
                    'end_time' => $toExclude['end_time'],
                ], $timeOptions);
                if ($key !== false) {
                    $timeOptions[$key]['excluded_by_rule'] = $toExclude['rule'];
                }
            }
        }

        //Order timeOptions by start time
        usort($timeOptions, function($a, $b) {
            return strtotime($a['start_time']) - strtotime($b['start_time']);
        });

        return $timeOptions;

    }

    private function excludeTimes($timeOptions, $startDate, $endDate, $rule){
        $timesToExclude = [];
        foreach ($timeOptions as $option) {

            $optionStart = Carbon::parse($option['start_time']);
            $optionEnd = Carbon::parse($option['end_time']);

            if (($optionStart >= $startDate && $optionStart < $endDate) || ($optionEnd > $startDate && $optionEnd <= $endDate) || ($optionStart <= $startDate && $optionEnd >= $endDate)) {
                //Remove this option from timeOptions
                $key = array_search($option, $timeOptions);
                if ($key !== false) {

                    $timesToExclude[$key]= [
                        'start_time' => $option['start_time'],
                        'end_time' => $option['end_time'],
                        'rule' => $rule
                    ];
                } else {
                    continue;
                }
            }
        }
        

        return $timesToExclude;
    }

    private function getRules($place_id)
    {
        $rules = ScheduleRules::whereHas('places', function ($query) use ($place_id) {
            // A coluna 'place_id' é verificada na tabela pivot 'place_schedule_rule'
            $query->where('place_id', $place_id); 
        })->get();

        return $rules;
    }

    private function getTimes($start_time, $end_time, $duration, $date = null){
        $timeOptions = [];
        $start = Carbon::createFromFormat('H:i:s', $start_time);
        $end = Carbon::createFromFormat('H:i:s', $end_time);
        $duration = Carbon::createFromFormat('H:i:s', $duration)->hour * 60 + Carbon::createFromFormat('H:i:s', $duration)->minute;
        //Calculate time slots
        $current = $start->copy();
        while ($current->lt($end)) {
            $slotEnd = $current->copy()->addMinutes($duration);
            if ($slotEnd->gt($end)) {
                break;
            }
            $timeOptions[] = [
                'start_time' => $current->format('H:i:s'),
                'end_time' => $slotEnd->format('H:i:s'),
            ];
            $current->addMinutes($duration);
        }

        return $timeOptions;
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