<?php

namespace App\Http\Controllers;

use App\Models\ScheduleRules;
use App\Http\Requests\StoreScheduleRulesRequest;
use App\Http\Requests\UpdateScheduleRulesRequest;
use App\Http\Controllers\ScheduleController;
use App\Models\Schedule;
use Illuminate\Http\Request;

class ScheduleRulesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreScheduleRulesRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(ScheduleRules $scheduleRules)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ScheduleRules $scheduleRules)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateScheduleRulesRequest $request, ScheduleRules $scheduleRules)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ScheduleRules $scheduleRules)
    {
        //
    }

    private function getRules($place_id)
    {
        $rules = ScheduleRules::whereHas('places', function ($query) use ($place_id) {
            // A coluna 'place_id' é verificada na tabela pivot 'place_schedule_rule'
            $query->where('place_id', $place_id); 
        })->get();

        return $rules;
    }

    public function getScheduledDates($place_id)
    {

        // Opcional: busca os registros do pivot junto com os dados da schedule_rules
        $withRules = $this->getRules($place_id)->where('type', 'include');

        $dates = [];

        foreach ($withRules as $item) {
            // Keep only the dates between start_date and end_date
            $currentDate = $item->start_date;
            $endDate = $item->end_date;
            while ($currentDate <= $endDate) {
                //Check if date is not already in the array and different from today is less than maximum_antecedence
                $diff = (strtotime($currentDate) - strtotime(date('Y-m-d'))) / (60 * 60 * 24);
                if ($diff >= 0 && $diff <= $item->maximum_antecedence && !in_array($currentDate, $dates)) {
                    $dates[] = $currentDate;
                }
                $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
            }

        }
        return response()->json($dates, 200);
    }

    public function getTimeOptions(Request $request)
    {
        $place_id = $request->input('place_id');
        $date = $request->input('date');
    
        $rules = $this->getRules($place_id);

        $timeOptions = [];
        $timeExclude = [];

        $weekdayNumber = date('w', strtotime($date)) + 1; // 0 (for Sunday) through 6 (for Saturday)

        $rules_include = $rules->where('type', 'include');

        $rules_exclude = $rules->where('type', 'exclude');

        foreach ($rules_exclude as $rule) {


            if (($date >= $rule->start_date && $date <= $rule->end_date && count($rule->weekdays) == 0) ||
                ($date >= $rule->start_date && $date <= $rule->end_date && $rule->weekdays->contains('id', $weekdayNumber)) ||
                (count($rule->weekdays) > 0 && $rule->weekdays->contains('id', $weekdayNumber)) ||
                (count($rule->weekdays) == 0 && $rule->start_date == null && $rule->end_date == null)) {
                    // Generate time slots based on start_time, end_time and interval
                    $startTime = strtotime($rule->start_time);
                    $endTime = strtotime($rule->end_time);

                    $timeExclude[] = [
                        $startTime,
                        $endTime,
                    ];
            }
        }

        $limit = 0;

        foreach ($rules_include as $rule) {
            if (($date >= $rule->start_date && $date <= $rule->end_date && count($rule->weekdays) == 0) ||
                ($date >= $rule->start_date && $date <= $rule->end_date && $rule->weekdays->contains('id', $weekdayNumber)) ||
                ($rule->weekdays->contains('id', $weekdayNumber))
                (count($rule->weekdays) == 0 && $rule->start_date == null && $rule->end_date == null)) 
            {
                // Generate time slots based on start_time, end_time and interval
                $startTime = strtotime($rule->start_time);
                $endTime = strtotime($rule->end_time);
                $duration = strtotime($rule->duration) - strtotime('00:00');

                if ($rule->quantity > $limit) {
                    $limit = $rule->quantity;
                }

                while ($startTime + $duration <= $endTime) {
                    // Check if the time slot overlaps with any exclude rule
                    $overlap = false;
                    foreach ($timeExclude as $exclude) {
                        if (!($startTime + $duration <= $exclude[0] || $startTime >= $exclude[1])) {
                            $overlap = true;
                            break;;
                        }
                    }   
                    if (!$overlap) {
                        $timeOptions[] = [date('H:i', $startTime), date('H:i', $startTime + $duration), 0];
                        $startTime += $duration;
                    } else {
                        $startTime = $exclude[1];
                    }
                    
                }
            }
        }


        foreach ($timeOptions as $key => $option) {
            // Update the timeOptions array with the availability
            $response = $this::checkColide($option[0], $option[1], $place_id, $date);
            $timeOptions[$key][2] = $response[0]; // member_id that has colide or 0
            $timeOptions[$key][3] = $response[1]; // status_id that has colide or null
        }


        return response()->json(
            ['options' => $timeOptions, 'quantity' => $limit]
        , 200);


    }

    private function checkColide($slotStartTime, $slotEndTime, $place_id, $date){
        $slotStart = strtotime($slotStartTime);
        $slotEnd = strtotime($slotEndTime);

        // Fetch existing schedules for the given place
        $existingSchedules = Schedule::where('place_id', $place_id)
            ->whereNotIn('status_id', [0, 4]) // Exclude cancelled and pending schedules
            ->whereDate('start_schedule', $date)
            ->get();

        foreach ($existingSchedules as $schedule) {
            $scheduleStart = strtotime(date('H:i', strtotime($schedule->start_schedule)));
            $scheduleEnd = strtotime(date('H:i', strtotime($schedule->end_schedule)));

            // Check for overlap
            if (!($slotEnd <= $scheduleStart || $slotStart >= $scheduleEnd)) {
                return [$schedule->member_id, $schedule->status_id]; // Collision detected
            }
        }


        return [0, null]; // No collision
    }
}
