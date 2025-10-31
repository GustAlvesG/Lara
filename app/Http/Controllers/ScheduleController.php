<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use App\Models\Place;
use App\Http\Requests\StoreScheduleRequest;
use App\Http\Requests\UpdateScheduleRequest;
use Illuminate\Http\Request;
use App\Models\ScheduleRules;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class ScheduleController extends Controller
{
    
    public function index()
    {

        $schedules_today = Schedule::whereBetween('start_schedule', [
            Carbon::today()->startOfDay(),
            Carbon::tomorrow()->endOfDay()
        ])->get();

        $schedules_today = $schedules_today->load('place.group', 'member');

        //Remove password from member
        $schedules_today->each(function ($schedule) {
            if ($schedule->member) {
                unset($schedule->member->password);
            }
        });

        //Group by place group
        // $schedules_today = $schedules_today->groupBy(function($item) {
        //     return $item->place->group->name;
        // });

        //Group by date
        $schedules_today = $schedules_today->groupBy(function($item) {
            return Carbon::parse($item->start_schedule)->format('Y-m-d');
        });

        //Inside each date group, group by place group
        $schedules_today = $schedules_today->map(function($dateGroup) {
            return $dateGroup->groupBy(function($item) {
                return $item->place->group->name;
            });
        });

        //Inside each place group, order by start_schedule
        $schedules_today = $schedules_today->map(function($dateGroup) {
            return $dateGroup->map(function($placeGroup) {
                return $placeGroup->sortBy('start_schedule');
            });
        });

        //Order by date
        $schedules_today = $schedules_today->sortKeys();
        
        // return response()->json(['schedules' => $schedules_today], 200);

        return view('location.index', compact('schedules_today'));
    }

    public function indexByPlace($place_id)
    {
        $schedules = Schedule::where('place_id', $place_id)->get();

        if ($schedules->isEmpty()) {
            return response()->json(['message' => 'No schedules found for this place.'], 404);
        }

        return response()->json(['schedules' => $schedules], 200);
    }

    /**
     * Display a listing of the resource.
     */
    public function indexByMember($member_id)
    {
        $schedules = Schedule::where('member_id', $member_id)->get();

        if ($schedules->isEmpty()) {
            return response()->json(['message' => 'No schedules found for this member.'], 404);
        }

        return response()->json(['schedules' => $schedules], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //Request can be an array with multiple schedule data or a single schedule
        $data = $request->all();
        
        //Drop user key if exists


        //Validate each schedule in the array
        $validatedSchedules = [];
        foreach ($data as $index => $scheduleData) {
            if ($index == "user") continue;

            $validator = \Validator::make($scheduleData, [
                'member_id' => 'required|int',
                'place_id' => 'required|integer',
                'start_schedule' => 'required|date',
                'end_schedule' => 'required|date|after:start_schedule',
                // 'status' => 'required|in:confirmed,cancelled,pending,expired',
                'price' => 'required|numeric|min:0',
            ]);



            if ($validator->fails()) {
                return response()->json([
                    'message' => "Validation failed for schedule at index $index",
                    'errors' => $validator->errors(),
                    'received_data' => $scheduleData,
                    'expected_fields' => ['member_id', 'place_id', 'start_schedule', 'end_schedule', 'status', 'price']
                ], 418);
            }


            $validatedSchedules[] = $validator->validated();
        }

        $createdSchedules = [];
        $errors = [];

        foreach ($validatedSchedules as $index => $validatedData) {
            //Check if the schedule collides with existing schedules
            $newSchedule = new Schedule($validatedData);

            if (!$this->checkColide($newSchedule)) {
                $errors[] = [
                    'index' => $index,
                    'message' => 'Schedule collides with existing schedules.',
                    'schedule' => $validatedData
                ];
                continue;
            }

            //Check if the schedule meets the rules (if any)
            $rulesCheck = $this->checkScheduleRules($newSchedule);

            if (!$rulesCheck) {
                $errors[] = [
                    'index' => $index,
                    'message' => 'Schedule does not meet the required rules.',
                    'schedule' => $validatedData
                ];
                continue;
            }



            //Create a new schedule
            $schedule = Schedule::create($validatedData);
            $createdSchedules[] = $schedule;
        }

        // Return response based on results
        if (empty($createdSchedules) && !empty($errors)) {
            return response()->json([
                'message' => 'No schedules were created due to validation errors.',
                'errors' => $errors,
            ], 418);
        }

        if (!empty($errors)) {
            return response()->json([
                'message' => 'Some schedules were created successfully, but some failed.',
                'created_schedules' => $createdSchedules,
                'errors' => $errors,
            ], 207); // Multi-Status
        }

        //Return the created schedules, message and status code
        return response()->json([
            'message' => count($createdSchedules) === 1 ? 'Schedule created successfully' : 'Schedules created successfully',
            'schedules' => $createdSchedules,
            'count' => count($createdSchedules),
        ], 201);
    }

    public function updateStatus(Request $request)
    {
        // Verifica se é um array ou objeto único
        $updates = $request->all();

        $updatedSchedules = [];
        $errors = [];
        foreach ($updates as $index => $updateData) {
             if ($index == "user") continue;
            $schedule = Schedule::find($updateData['id']);
    
            if (!$schedule) {
                $errors[] = [
                    'index' => $index,
                    'id' => $updateData['id'],
                    'message' => 'Schedule not found.'
                ];
                continue;
            }
    
            $schedule->status = $updateData['status'];
            $schedule->save();
            $updatedSchedules[] = $schedule;
        }
    
        // Retorna resposta baseada nos resultados
        if (empty($updatedSchedules) && !empty($errors)) {
            return response()->json([
                'message' => 'No schedules were updated due to errors.',
                'errors' => $errors,
            ], 404);
        }
    
        if (!empty($errors)) {
            return response()->json([
                'message' => 'Some schedules were updated successfully, but some failed.',
                'updated_schedules' => $updatedSchedules,
                'errors' => $errors,
            ], 207); // Multi-Status
        }
    
        return response()->json([
            'message' => count($updatedSchedules) === 1 ? 'Schedule status updated successfully.' : 'Schedules status updated successfully.',
            'schedules' => $updatedSchedules,
            'count' => count($updatedSchedules)
        ], 200);
    }

    public function destroyPending(Request $request)
    {

        $schedule = Schedule::where('id', $request->id)
            ->where('status', '3')
            ->first();
        if (empty($schedule)) {
            return response()->json(['message' => 'No pending schedules found with the provided ID.'], 404);
        }

        $schedule->delete();

        return response()->json(['message' => 'Pending schedule deleted successfully.'], 200);
    }

    

    private function checkColide($schedule){
        // Check if the new schedule collides with existing schedules
        $existingSchedules = Schedule::where('place_id', $schedule->place_id)
            ->where(function ($query) use ($schedule) {
                $query->where(function ($query) use ($schedule) {
                          // Verifica se o novo agendamento começa durante um agendamento existente
                          $query->where('start_schedule', '<', $schedule->start_schedule)
                                ->where('end_schedule', '>', $schedule->start_schedule);
                      })
                      ->orWhere(function ($query) use ($schedule) {
                          // Verifica se o novo agendamento termina durante um agendamento existente  
                          $query->where('start_schedule', '<', $schedule->end_schedule)
                                ->where('end_schedule', '>', $schedule->end_schedule);
                      })
                      ->orWhere(function ($query) use ($schedule) {
                          // Verifica se o novo agendamento engloba completamente um agendamento existente
                          $query->where('start_schedule', '>=', $schedule->start_schedule)
                                ->where('end_schedule', '<=', $schedule->end_schedule);
                      });
            })
            ->exists();

        return !$existingSchedules;
    }

    private function checkScheduleRules($schedule)
    {
        

        // Check if the place has any schedule rules
        $place = Place::find($schedule->place_id)->load('scheduleRules');

        #Order the rules by type
        $rules_exclude  = $place->scheduleRules->where('type', 'exclude');
        $rules_include  = $place->scheduleRules->where('type', 'include');

        $scheduleStart = Carbon::parse($schedule->start_schedule);
        $scheduleEnd   = Carbon::parse($schedule->end_schedule);

        $scheduleStartDate = $scheduleStart->format('Y-m-d');
        $scheduleEndDate   = $scheduleEnd->format('Y-m-d');
        $scheduleStartTime = $scheduleStart->format('H:i:s');
        $scheduleEndTime   = $scheduleEnd->format('H:i:s');
        $scheduleWeekday = strtolower($scheduleStart->format('l')); // Get the day of the week in lowercase

        $response = true; // Default response to false, meaning no collision detected
        foreach ($rules_exclude as $rule) {


            $ruleStartDate = Carbon::parse($rule->start_date)->format('Y-m-d');
            $ruleEndDate   = Carbon::parse($rule->end_date)->format('Y-m-d');
            $ruleStartTime = Carbon::parse($rule->start_time)->format('H:i:s');
            $ruleEndTime   = Carbon::parse($rule->end_time)->format('H:i:s');
            $ruleWeekday = $rule->weekdays; // Assuming weekday is stored as a string (e.g., 'Monday', 'Tuesday', etc.)
            //Turn weekday into array with the tag name
            $ruleDays = [];
            foreach($ruleWeekday as $day) {
                $ruleDays[] = $day->name;
            }
            $response = false; // Default response to false, meaning collision detected
            //Check if has Start and End Time
            if ($ruleStartDate && $ruleEndDate) {
                $valid_period = $this->checkPeriod($scheduleStartDate, $scheduleEndDate, $ruleStartDate, $ruleEndDate);
            }
        
            if (isset($rule->weekdays)) {
                $valid_weekday = $this->checkWeekday($scheduleWeekday, $ruleDays);
            } 
            if ($ruleStartTime && $ruleEndTime) {
                $valid_time = $this->checkTime($scheduleStartTime, $scheduleEndTime, $ruleStartTime, $ruleEndTime);
            }

            // P - -
            if ((($ruleStartDate && $ruleEndDate) &&  $valid_period) || 
                (!($ruleStartDate && $ruleEndDate) && isset($rule->weekdays) && !($ruleStartTime && $ruleEndTime) && $valid_weekday) ||
                (!($ruleStartDate && $ruleEndDate) && !isset($rule->weekdays) && ($ruleStartTime && $ruleEndTime) && $valid_time) ||
                (($ruleStartDate && $ruleEndDate) && isset($rule->weekdays) && !($ruleStartTime && $ruleEndTime) && $valid_weekday) ||
                (($ruleStartDate && $ruleEndDate) && !isset($rule->weekdays) && ($ruleStartTime && $ruleEndTime) && $valid_time) ||
                (!($ruleStartDate && $ruleEndDate) && isset($rule->weekdays) && ($ruleStartTime && $ruleEndTime) && $valid_time) ||
                ($valid_period || $valid_weekday || $valid_time)
            ) {
                continue; 
            } else {
                $response = false;
            }
        }
        if ($response){
            $response = false; // Default response to false
            foreach ($rules_include as $rule) {
                $ruleStartDate = Carbon::parse($rule->start_date)->format('Y-m-d');
                $ruleEndDate   = Carbon::parse($rule->end_date)->format('Y-m-d');
                $ruleStartTime = !empty($rule->start_time) ? Carbon::parse($rule->start_time)->format('H:i:s') : null;
                $ruleEndTime   = !empty($rule->end_time) ? Carbon::parse($rule->end_time)->format('H:i:s') : null;

                $ruleWeekday = $rule->weekdays; // Assuming weekday is stored as a string (e.g., 'Monday', 'Tuesday', etc.)
                //Turn weekday into array with the tag name
                $ruleDays = [];
                foreach($ruleWeekday as $day) {
                    $ruleDays[] = $day->name;
                }
                
                //Check if has Start and End Time
                if ($ruleStartDate && $ruleEndDate) {
                    $valid_period = !$this->checkPeriod($scheduleStartDate, $scheduleEndDate, $ruleStartDate, $ruleEndDate);
                }
                else {
                    $valid_period = true; // If no period is set, consider it valid
                }
            
                if (count($ruleDays) > 0) {
                    $valid_weekday = !$this->checkWeekday($scheduleWeekday, $ruleDays);
                } 
                else {
                    $valid_weekday = true; // If no weekday is set, consider it valid
                }
                if ($ruleStartTime && $ruleEndTime) {
                    $valid_time = !$this->checkTime($scheduleStartTime, $scheduleEndTime, $ruleStartTime, $ruleEndTime);
                }
                else {
                    $valid_time = true; // If no time is set, consider it valid
                }

                $valid_antecedence = $this->checkAntecedence($scheduleStart, $rule->maximium_antecedence);

                $valid_duration = $this->checkDuration($scheduleStart, $scheduleEnd, $rule->duration);

                if ($valid_period && $valid_weekday && $valid_time && $valid_antecedence && $valid_duration) {
                    $response = true;
                    break; // If one include rule is satisfied, no need to check further
                }
            }
        } 
        return $response;
    }

    private function checkPeriod($startDate, $endDate, $ruleStartDate, $ruleEndDate)
    {
        return (
            ($startDate > $ruleEndDate && $endDate > $ruleEndDate) ||
            ($endDate < $ruleStartDate && $startDate < $ruleStartDate)
        );

    }

    private function checkWeekday($scheduleWeekday, $ruleWeekday)
    {
        // Check if the schedule weekday is in the rule weekdays
        return !in_array($scheduleWeekday, $ruleWeekday);
    }

    private function checkTime($startTime, $endTime, $ruleStartTime, $ruleEndTime)
    {
        // Check if the schedule time is within the rule time
        return (($startTime > $ruleEndTime && $startTime > $ruleEndTime) ||
            ($endTime < $ruleStartTime && $endTime < $ruleStartTime)
        );
    }

    private function checkAntecedence($scheduleStart, $ruleAntecedence)
    {
        $today = Carbon::today();
        $differenceDays = $today->diffInDays($scheduleStart);
        return $differenceDays >= $ruleAntecedence;
    }

    private function checkDuration($scheduleStart, $scheduleEnd, $ruleDuration)
    {
        $differenceInHours = $scheduleStart->diffInHours($scheduleEnd);
        //Convert rule duration to decimal hours
        $ruleDuration = (float) $ruleDuration;
        return $differenceInHours <= $ruleDuration;
    }
};
