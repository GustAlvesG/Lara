<?php

namespace App\Http\Controllers;

use App\Models\ScheduleRules;
use App\Http\Requests\StoreScheduleRulesRequest;
use App\Http\Requests\UpdateScheduleRulesRequest;
use App\Http\Controllers\ScheduleController;
use App\Models\Schedule;
use Illuminate\Http\Request;
use App\Services\ScheduleRulesService;
use App\Services\SchedulesService;

class ScheduleRulesController extends Controller
{

    public function __construct(ScheduleRulesService $scheduleRuleService, SchedulesService $schedulesService)
    {
        $this->scheduleRuleService = $scheduleRuleService;
        $this->schedulesService = $schedulesService;
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
            
            // Return to place-group show with success message
            return redirect()->route('place-group.show', ['place_group' => $response['place_group_id']])
                             ->with('success', 'Regra criada com sucesso!');

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
            $member_id = $request->input('member_id');

            $timeOptions = $this->scheduleRuleService->getTimeOptions($place_id, $date, $member_id);

            $limit = $this->scheduleRuleService->getLimit($place_id, $request->input('member_id'), $date);

        }
        catch (\Exception $e) {
            return response()->json(['error' => 'Invalid input: ' . $e->getMessage()], 400);
        }
        
        return response()->json(
            ['options' => $timeOptions, 'limit' => $limit]
        , 200);


    }

}
