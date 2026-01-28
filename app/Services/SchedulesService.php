<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Schedule;
use App\Models\Place;
use App\Models\Member;
use App\Models\ScheduleRules;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use PDF; // Facade do pacote barryvdh/laravel-dompdf
use App\Http\Controllers\MemberController;
use App\Services\RuleValidationService as RuleCheck;

class SchedulesService
{
    
    public function getShedulesByPlace($place_id, $date = null){
        $schedules = Schedule::where('place_id', $place_id)
            ->when($date, function ($query) use ($date) {
                return $query->whereDate('start_schedule', $date->toDateString());
            })
            ->get();

        if ($schedules->isEmpty()) {
            return response()->json(['message' => 'No schedules found for this place.'], 404);
        }

        return response()->json(['schedules' => $schedules], 200);
    }

    public function getScheduleByMember($member_id){
        $schedules = Schedule::withoutGlobalScopes()
            ->where('member_id', $member_id)->get();
            $schedules = $schedules->load(['place']);
            if ($schedules->isEmpty()) {
                return response()->json(['message' => 'No schedules found for this member.'], 404);
            }
            return response()->json(['schedules' => $schedules], 200);
    }

    public function getSchedulesInRange($date)
    {

    }

    public function getSchedules($date = null)
    {
        if (!$date) {
            $date = Carbon::now()->toDateString();
        }

        $allPlacesAndTimes = Place::with(['group'])->get();

        foreach ($allPlacesAndTimes as $place) {
            $options = $this->getTimeOptions(new Request([
                'place_id' => $place->id,
                'date' => $date,
            ]));
            $place->time_options = $options;
        }

        //Group by place group
        $allPossibleSchedules = $allPlacesAndTimes->groupBy(function ($item) {
            return $item->group->name;
        });

        return $allPossibleSchedules;
    }

    public function createScheduleFromWeb(Request $request)
    {
        if (isset($request['cpf'])) {
            //Clean cpf to have only numbers
            $member_id = Member::where('cpf', preg_replace('/\D/', '', $request['cpf']))->value('id');
            if (!$member_id) {
                $response = MemberController::store($request['cpf'], $request['title'], $request['birthDate']);       
                //Type response
                if ($response->getStatusCode() == 201) {
                    $member_id = $response->getData()->user->id;
                } else {
                    return $response;
                }
            }
            $request['member_id'] = $member_id;
        }
        foreach ($request->input('selected_slots') as $slot) {
            $schedule = new Schedule();
            $schedule->place_id = $request->input('place_id');
            $schedule->member_id = $request['member_id']; // member_id is passed in title field
            // $schedule->start_schedule = $request->input('date') . ' ' . $request->input('start_time');
            // $schedule->end_schedule = $request->input('date') . ' ' . $request->input('end_time');
            $schedule->status_id = $request->input('status_id', 1); // Default to 1 (confirmed) if not provided
            $schedule->price = $request->input('price', 0); // Default to 0 if not provided

            $schedule->start_schedule = $request->input('date') . ' ' . explode(" - ", $slot)[0];
            $schedule->end_schedule = $request->input('date') . ' ' . explode(" - ", $slot)[1];
            $schedule->save();
        }

        

        return $schedule;
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
                    $startTime = $rule->start_time != null ? strtotime($rule->start_time) : strtotime('00:00:00');
                    $endTime = $rule->end_time != null ? strtotime($rule->end_time) : strtotime('23:59:59');

                    $timeExclude[] = [
                        $startTime,
                        $endTime,
                        $rule->name ?? 'Regra sem Nome' // Adiciona o nome da regra
                    ];
                    // dd($timeExclude, $rule);
            }
        }

        // dd($timeExclude);


        foreach ($rules_include as $rule) {
            $diffInDays = Carbon::now()->startOfDay()->diffInDays(Carbon::parse($date)->startOfDay(), false);

            if($rule->maximum_antecedence == null){
                $rule->maximum_antecedence = 0;
            }

            if ($rule->minimum_antecedence == null){
                $rule->minimum_antecedence = 0;
            }

            // if ($rule->minimum_antecedence != null && $rule->minimum_antecedence > 0) {
            if ($diffInDays < $rule->minimum_antecedence) {
                continue; // Pula esta regra, pois a antecedência mínima não é atendida
            }
            // }
            
            // if ($rule->maximum_antecedence != null && $rule->maximum_antecedence > 0) {
            if ($diffInDays > $rule->maximum_antecedence) {
                continue; // Pula esta regra, pois a antecedência máxima não é atendida
            }
            

            if (($date >= $rule->start_date && $date <= $rule->end_date && count($rule->weekdays) == 0) ||
                ($date >= $rule->start_date && $date <= $rule->end_date && $rule->weekdays->contains('id', $weekdayNumber)) ||
                ($rule->weekdays->contains('id', $weekdayNumber)) ||
                (count($rule->weekdays) == 0 && $rule->start_date == null && $rule->end_date == null)) 
            {
                // Generate time slots based on start_time, end_time and interval
                $startTime = strtotime($rule->start_time);
                $endTime = strtotime($rule->end_time);
                $duration = strtotime($rule->duration) - strtotime('00:00');

                while ($startTime + $duration <= $endTime) {
                    // Check if the time slot overlaps with any exclude rule
                    $overlap = false;
                    $excludeRule = null;
                    foreach ($timeExclude as $exclude) {
                        if (!($startTime + $duration <= $exclude[0] || $startTime >= $exclude[1])) {
                            $overlap = true;
                            $excludeRule = $exclude;
                            break;;
                        }
                    }   
                    if (!$overlap) {
                        $timeOptions[] = [
                            'start_time' => date('H:i', $startTime), 
                            'end_time' => date('H:i', $startTime + $duration),
                            ];
                        $startTime += $duration;
                    } else {
                        // Adiciona o registro da exclusão com o nome da regra
                        $timeOptions[] = [
                            'start_time' => date('H:i', $excludeRule[0]), 
                            'end_time' => date('H:i', $excludeRule[1]), 
                            'rule_to_block' => $excludeRule[2], // Nome da regra
                            'blocked' => true // Marcador para não verificar colisão depois
                        ];
                        $startTime = $excludeRule[1];
                    }
                    
                }
            }
        }

        
        foreach ($timeOptions as $key => $option) {
            // Se for um horário bloqueado por regra, pula a verificação de colisão
            if (isset($option[3]) && $option[3] === 'blocked') {
                continue;
            }

            // Update the timeOptions array with the availability
            $response = $this::checkColide($option['start_time'], $option['end_time'], $place_id, $date);
            $timeOptions[$key]['member'] = $response[0]; // member_id that has colide or 0
            $timeOptions[$key]['status'] = $response[1]; // status_id that has colide or null
        }


        return $timeOptions;
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
                $member = Member::find($schedule->member_id);
                return [$member, $schedule->status_id]; // Collision detected
            }
        }


        return [null, null]; // No collision
    }

    private function getRules($place_id)
    {
        $rules = ScheduleRules::whereHas('places', function ($query) use ($place_id) {
            // A coluna 'place_id' é verificada na tabela pivot 'place_schedule_rule'
            $query->where('place_id', $place_id); 
        })->get();

        return $rules;
    }


}

