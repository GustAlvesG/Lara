<?php

namespace App\Http\Controllers;

use App\Models\ScheduleRules;
use App\Http\Requests\StoreScheduleRulesRequest;
use App\Http\Requests\UpdateScheduleRulesRequest;
use App\Http\Controllers\ScheduleController;
use App\Models\Schedule;
use Illuminate\Http\Request;
use App\Services\ScheduleRulesService;

class ScheduleRulesController extends Controller
{

    public function __construct(ScheduleRulesService $scheduleRuleService)
    {
        $this->scheduleRuleService = $scheduleRuleService;
    }
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
        try{

            $response = $this->scheduleRuleService->store($request);
            return $response;

        } catch (\Exception $e) {
            // Handle the exception or log it
            return redirect()->back()->withErrors(['error' => 'Failed to create Schedule Rule: ' . $e->getMessage()]);
        }
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
        try {
            $place_id = $request->input('place_id');
            $date = $request->input('date') ?? date('Y-m-d');

            $timeOptions = $this->scheduleRuleService->getTimeOptions($place_id, $date);

            foreach ($timeOptions as $key => $option) {
                //Check if time option colides with existing schedules
                list($member, $status_id) = $this->checkColide($option['start_time'], $option['end_time'], $place_id, $date);
                if ($member) {
                    $timeOptions[$key]['colides'] = true;
                    $timeOptions[$key]['colided_member'] = $member;
                    $timeOptions[$key]['colided_status_id'] = $status_id;
                }
                else {
                    $timeOptions[$key]['colides'] = false;
                }
            }
        }
        catch (\Exception $e) {
            return response()->json(['error' => 'Invalid input: ' . $e->getMessage()], 400);
        }
        

        dd($timeOptions);

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
                return [$schedule->member, $schedule->status_id]; // Collision detected
            }
        }


        return [null, null]; // No collision
    }
}
