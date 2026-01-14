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
use PDF; // Facade do pacote barryvdh/laravel-dompdf
use App\Models\Member;
use App\Http\Controllers\MemberController;


class ScheduleController extends Controller
{
    
    public function index()
    {
        $rangeStart = Carbon::today()->startOfDay();
        $rangeEnd   = Carbon::tomorrow()->endOfDay();

        $schedules = $this->schedules_today();

        return view('location.index', compact('schedules'));
    }

    public function index_api()
    {
        $schedules = $this->schedules_today();

        return response()->json(['schedules' => $schedules], 200);
    }

    public function indexByPlace(Request $request)
    {

        #Validate place_id
        $validator = Validator::make($request->all(), [
            'place_id' => 'required|integer|exists:places,id',
            'date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 418);
        }

        $place_id = $request->input('place_id');
        if ($request->has('date')) {
            $date = Carbon::parse($request->input('date'));
        } else {
            # Default to everydate
            $date = null;
        }


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



    /**
     * Display a listing of the resource.
     */
    public function indexByMember($member_id)
    {
        $schedules = Schedule::withoutGlobalScopes()
        ->where('member_id', $member_id)->get();
        $schedules = $schedules->load(['place']);
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

        //Validate each schedule in the array
        $validatedSchedules = [];
        foreach ($data as $index => $scheduleData) {
            if ($index == "user") continue;

            if (isset($scheduleData['cpf'])) {
                //Clean cpf to have only numbers
                $member_id = Member::where('cpf', preg_replace('/\D/', '', $scheduleData['cpf']))->value('id');
                if (!$member_id) {
                    $response = MemberController::store($scheduleData['cpf'], $scheduleData['title'], $scheduleData['birthDate']);       
                    //Type response
                    if ($response->getStatusCode() == 201) {
                        $member_id = $response->getData()->user->id;
                    } else {
                        return $response;
                    }
                }
                $scheduleData['member_id'] = $member_id;
            }

            $validator = \Validator::make($scheduleData, [
                'member_id' => 'required|int',
                'place_id' => 'required|integer',
                'start_schedule' => 'required|date',
                'end_schedule' => 'required|date|after:start_schedule',
                'status_id' => 'required|in:0,1,3,4',
                'price' => 'required|numeric|min:0',
                'cpf' => 'nullable|string|max:14',
                'title' => 'nullable|string|max:8',
                'birthDate' => 'nullable|date'
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
            $rules = $this->checkScheduleRules($newSchedule);
            $rulesCheck = $rules['result'];
            $rule_report = $rules['report'];



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
                'data_received' => $data,
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

    public function create()
    {
        return view('location.schedule.create');
    }

    public function show($id)
    {
        $schedule = Schedule::with(['status','place.group','member'])->find($id);

        if (!$schedule) {
            return response()->json(['message' => 'Schedule not founds.'], 404);
        }

        //Get other schedules of the same member in the same date
        $other_schedules = Schedule::with(['status','place.group','member'])
            ->where('member_id', $schedule->member_id)
            ->whereDate('start_schedule', Carbon::parse($schedule->start_schedule)->toDateString())
            ->where('id', '!=', $schedule->id)
            ->get();

        //Add current schedule to the other schedules in first position
        $other_schedules->prepend($schedule);


        return view('location.schedule.show', compact('schedule', 'other_schedules'));
    }

    public function update(Request $request)
    {
        $selectedReservations = $request->input('selected_reservations', []);
        $actionStatus = $request->input('action_status');

        if (empty($selectedReservations) || empty($actionStatus)) {
            return redirect()->back()->with('error', 'Por favor, selecione ao menos uma reserva e uma ação para executar.');
        }

        $statusMap = [
            'confirmar' => 1,
            'cancelar' => 0,
        ];

        if (!array_key_exists($actionStatus, $statusMap)) {
            return redirect()->back()->with('error', 'Ação inválida selecionada.');
        }

        $newStatusId = $statusMap[$actionStatus];
        $updatedCount = 0;


        foreach ($selectedReservations as $reservationId) {
            $schedule = Schedule::find($reservationId);
            if ($schedule) {
                $schedule->status_id = $newStatusId;
                $schedule->save();
                $updatedCount++;
            }
        }

        return redirect()->back()->with('success', "{$updatedCount} reservas atualizadas com sucesso.");
    }

    public function indexFilter(Request $request)
    {

        //Check if any filter is applied
        if (!$request->filled('place_group_id') && 
            !$request->filled('status') && 
            !$request->filled('start_schedule') && 
            !$request->filled('member_cpf')) {
            return redirect()->route('schedule.index');
        }

        if (!$request->filled('start_schedule') && 
            !$request->filled('member_cpf') &&
            ($request->filled('place_group_id') || $request->filled('status'))) {
            $request->merge(['start_schedule' => Carbon::today()->toDateString()]);
        }

        //place_group_id, status, start_schedule, member_cpf
        $schedules = Schedule::with(['status','place.group','member'])
            ->when($request->filled('place_group_id'), function ($q) use ($request) {
                $q->whereHas('place.group', function ($q2) use ($request) {
                    $q2->where('id', $request->input('place_group_id'));
                });
            })
            ->when($request->filled('status'), function ($q) use ($request) {
                $q->where('status_id', $request->input('status'));
            })
            ->when($request->filled('start_schedule'), function ($q) use ($request) {
                $date = Carbon::parse($request->input('start_schedule'));
                $q->whereDate('start_schedule', $date->toDateString());
            })
            ->when($request->filled('member_cpf'), function ($q) use ($request) {
                $cpf = preg_replace('/\D/', '', $request->input('member_cpf'));
                $q->whereHas('member', function ($q2) use ($cpf) {
                    $q2->where('cpf', 'like', "%{$cpf}%");
                });
            })->get()
            ->groupBy(fn($s) => Carbon::parse($s->start_schedule)->toDateString())
            ->map(fn($dateGroup) =>
                $dateGroup
                    ->groupBy(fn($s) => optional($s->place->group)->name ?? 'Sem Grupo')
                    ->map(fn($placeGroup) => $placeGroup->sortBy('start_schedule'))
            )
            ->sortKeys();


        return view('location.index', compact('schedules'));
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
    
            $schedule->status_id = $updateData['status_id'];
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
            ->where('status_id', '3')
            ->first();
        if (empty($schedule)) {
            return response()->json(['message' => 'No pending schedules found with the provided ID.'], 404);
        }

        $schedule->delete();

        return response()->json(['message' => 'Pending schedule deleted successfully.'], 200);
    }

    private function checkColide($schedule){
        // Check if the new schedule collides with existing schedules, excluding those with status 4, 0
        $existingSchedules = Schedule::where('place_id', $schedule->place_id)
            ->whereNotIn('status_id', [0, 4])
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
        $rule_report = [];
        // Check if the place has any schedule rules except status_id 2 (disabled)
        $place = Place::find($schedule->place_id)->load(['scheduleRules' => function ($query) {
            $query->where('status_id', 1);
        }]);

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
            $rule_report[$rule->id]['type'] = 'exclude';

            $ruleStartDate = !empty($rule->start_date) ? Carbon::parse($rule->start_date)->format('Y-m-d') : null;
            $ruleEndDate   = !empty($rule->end_date) ? Carbon::parse($rule->end_date)->format('Y-m-d') : null;
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
                $valid_period = $this->checkPeriod($scheduleStartDate, $scheduleEndDate, $ruleStartDate, $ruleEndDate);
                $rule_report[$rule->id]['period'] = $valid_period;
            }
            else {
                $valid_period = true; // If no period is set, consider it valid
            }
        
            if (count($ruleDays) > 0) {
                $valid_weekday = $this->checkWeekday($scheduleWeekday, $ruleDays);
                $rule_report[$rule->id]['weekday'] = $valid_weekday;
                
            } 
            else {
                $valid_weekday = true; // If no weekdays are set, consider it valid
            }   
            if ($ruleStartTime && $ruleEndTime) {
                $valid_time = $this->checkTime($scheduleStartTime, $scheduleEndTime, $ruleStartTime, $ruleEndTime);
                $rule_report[$rule->id]['time'] = $valid_time;
            }
            else {
                $valid_time = true; // If no time is set, consider it valid
            }

            
            //Tenho uma regra de periodo e estou no periodo bloqueado
            if ($ruleStartDate && $ruleEndDate && !$valid_period) {
                //Tenho uma regra de dias da semana e estou no dia bloqueado
                $teste[] = "ha periodo bloqueado";
                if(count($ruleDays) > 0  && !$valid_weekday){
                    $teste[] = "ha dia bloqueado";
                    //Tenho uma regra de horario e nao estou no horario bloqueado
                    $response = ($ruleStartTime && $ruleEndTime && $valid_time);
                    

                //Nao tenho regra de dias da semana
                } else if (!(count($ruleDays) > 0) ){
                    $teste[] = "nao ha dia bloqueado";
                    $response = ($ruleStartTime && $ruleEndTime && $valid_time);
                }
            //Nao tem um periodo definido
            } else{
                $teste[] = "nao ha periodo bloqueado";
                if(count($ruleDays) > 0  && !$valid_weekday){
                    $teste[] = "ha dia bloqueado";
                    $response = ($ruleStartTime && $ruleEndTime && $valid_time);
                //Nao tenho regra de dias da semana
                } else if (!(count($ruleDays) > 0) ){
                    $teste[] = "nao ha dia bloqueado";
                    $response = ($ruleStartTime && $ruleEndTime && $valid_time);
                }
            }
        }

        if ($response){
            $response = false; // Default response to false
            foreach ($rules_include as $rule) {
                $rule_report[$rule->id]['type'] = 'include';
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
                $rule_report[$rule->id]['period'] = $valid_period;

            
            
                if (count($ruleDays) > 0) {
                    $valid_weekday = !$this->checkWeekday($scheduleWeekday, $ruleDays);
                } 
                else {
                    $valid_weekday = true; // If no weekday is set, consider it valid
                }
                $rule_report[$rule->id]['weekday'] = $valid_weekday;
                if ($ruleStartTime && $ruleEndTime) {
                    $valid_time = !$this->checkTime($scheduleStartTime, $scheduleEndTime, $ruleStartTime, $ruleEndTime);
                }
                else {
                    $valid_time = true; // If no time is set, consider it valid
                }
                $rule_report[$rule->id]['time'] = $valid_time;

                $valid_antecedence = $this->checkAntecedence($scheduleStart, $rule->maximium_antecedence);
                $rule_report[$rule->id]['antecedence'] = $valid_antecedence;

                $valid_duration = $this->checkDuration($scheduleStart, $scheduleEnd, $rule->duration);
                $rule_report[$rule->id]['duration'] = $valid_duration;

                
                if ($valid_period && $valid_weekday && $valid_time && $valid_antecedence && $valid_duration) {
                    $response = true;
                    break; // If one include rule is satisfied, no need to check further
                }
            }
        } 
        
        return ['result' => $response, 'report' => $rule_report];
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
        return (($startTime > $ruleEndTime && $endTime > $ruleEndTime) ||
            ($endTime < $ruleStartTime && $startTime < $ruleStartTime)
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
        //Convert rule duration string to decimal hours
        $ruleDurationTime = Carbon::createFromFormat('H:i:s', $ruleDuration);
        $ruleDuration = $ruleDurationTime->hour + ($ruleDurationTime->minute / 60);
        return $differenceInHours <= $ruleDuration;
    }

    private function schedules_today()
    {
        $rangeStart = Carbon::today()->startOfDay();
        $rangeEnd   = Carbon::tomorrow()->endOfDay();

        $schedules_today = Schedule::with(['status','place.group','member'])
            ->whereBetween('start_schedule', [$rangeStart, $rangeEnd])
            ->whereIn('status_id', [1, 3])
            ->orderBy('start_schedule')
            ->get()
            ->groupBy(fn($s) => Carbon::parse($s->start_schedule)->toDateString())
            ->map(fn($dateGroup) =>
                $dateGroup
                    ->groupBy(fn($s) => optional($s->place->group)->name ?? 'Sem Grupo')
                    ->map(fn($placeGroup) => $placeGroup->sortBy('start_schedule'))
            )
            ->sortKeys();

        return $schedules_today;
    }

    public function getScheduledDates($place_id)
    {
        $dates = Schedule::where('place_id', $place_id)
            ->selectRaw('DATE(start_schedule) as date')
            ->distinct()
            ->pluck('date');

        return response()->json(['scheduled_dates' => $dates], 200);
    }

    public function generateDailySchedulePDF(Request $request)
    {
        // 1. Define a data de hoje e os limites de tempo.
        $today = Carbon::today();
        $startOfDay = $today->startOfDay()->toDateTimeString();
        $endOfDay = $today->endOfDay()->toDateTimeString();

        // 2. Busca os agendamentos para o dia de hoje no banco de dados.
        // Carrega os relacionamentos necessários.
        $dailySchedules = Schedule::whereBetween('start_schedule', [$startOfDay, $endOfDay])
            ->where('status_id', 1) // Considera apenas agendamentos confirmados
            ->with(['member', 'place', 'status'])
            ->orderBy('start_schedule', 'asc')
            ->get();

        

        // 3. Verifica se há agendamentos para evitar PDFs vazios.
        if ($dailySchedules->isEmpty()) {
             return response()->json([
                'message' => 'Nenhum agendamento encontrado para a data de hoje.',
                'date' => Carbon::now()->format('d/m/Y')
             ], 404);
        }

        // 4. Agrupa por Place Group ID e depois sub-agrupa por Place Name.
        // Isso garante a organização hierárquica solicitada.
        $groupedSchedules = $dailySchedules
            // Agrupamento principal: Place Group ID (ou 0 se for null)
            ->groupBy(fn ($schedule) => $schedule->place->group->name ?? 'Sem Grupo') 
            // Sub-agrupamento: Place Name
            ->map(fn ($group) => $group->groupBy(fn ($schedule) => $schedule->place->name ?? 'Local Desconhecido'));

        // 5. Dados a serem passados para a view do PDF
        $data = [
            'date' => Carbon::now()->format('d/m/Y'),
            'groupedSchedules' => $groupedSchedules // Passando os dados AGRUPADOS
        ];

        // 6. Gera o PDF usando o Dompdf
        $pdf = PDF::loadView('template_calendar', $data);

        // 7. Retorna o PDF para o navegador.
        $fileName = 'Calendario_Dia_' . Carbon::now()->format('Ymd') . '.pdf';
        return $pdf->stream($fileName);
    }
}
