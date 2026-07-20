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
use App\Services\ScheduleRulesService;
use App\Services\MemberService;
use App\Services\EmailService;
use App\Services\RedeItauService;


class SchedulesService
{

    protected $scheduleRulesService;
    protected $memberService;
    protected $emailService;
    protected $redeItauService;

    public function __construct()
    {
        $this->scheduleRulesService = new ScheduleRulesService();
        $this->memberService = new MemberService();
        $this->emailService = new EmailService();
        $this->redeItauService = new RedeItauService();
    }

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

    public function updateSchedulesStatus($data){
        $schedules_ids = [];
        $schedules = [];
        $user = Auth()->user();
        $payments_ids = [];
        foreach ($data['selected_reservations'] as $schedule_id) {
            $schedule = Schedule::find($schedule_id);
            if ($schedule) {
                $schedule->status_id = $data['action_status'];
                
                $schedule->updated_by_user = $user->id;
                
                $schedule->save();

                if(isset($data['refund_payment'])){
                    if (!in_array($schedule->schedule_payment_id, $payments_ids)) {
                        $payments_ids[] = $schedule->schedule_payment_id;
                    }
                }
            }
        }
        $response = [];
        if (isset($data['refund_payment']) && count($payments_ids) > 0)
            $response = $this->redeItauService->beginRefund($payments_ids);

        return $response;
    }

    public function getSchedules($date = null)
    {
        if (!$date) {
            $date = Carbon::now()->toDateString();
        }

        $allPlacesAndTimes = Place::where('status_id', 1)->with(['group'])->get();

        foreach ($allPlacesAndTimes as $place) {
            $options = $this->scheduleRulesService->getTimeOptions($place->id, $date);
            $place->time_options = $options;
        }

        //Group by place group
        $allPossibleSchedules = $allPlacesAndTimes->groupBy(function ($item) {
            return $item->group->name;
        });
        return $allPossibleSchedules;
    }

    public function createSchedule(Request $request)
    {
        if (isset($request['cpf'])) { 
            //Clean cpf to have only numbers
            $request['member_id'] = $this->memberService->memberByCpf($request);
        }

        $schedules = [];
        $mailData = [];

        foreach ($request->input('selected_slots') as $slot) {
            $time_start = explode(" - ", $slot)[0];
            $time_end = explode(" - ", $slot)[1];
            if (!$this->checkColide($time_start, $time_end, $request['place_id'], $request->input('date'), $request['member_id'])[0] == null) {
                throw new \Exception("Horário colide com outro agendamento.");
            }
            if (!$this->isValidScheduleTime($request['place_id'], $time_start, $time_end, $request->input('date'))) {
                throw new \Exception("Horário inválido para o local selecionado.");
            }
            if (Auth()->check()) {
                $request['created_by_user'] = Auth()->user()->id;
            }

            $schedules[] = $this->store(new Request([
                'place_id' => $request['place_id'],
                'member_id' => $request['member_id'],
                'start_schedule' => $request->input('date') . ' ' . $time_start,
                'end_schedule' => $request->input('date') . ' ' . $time_end,
                'status_id' => $request->input('status_id') ?? 1,
                // Se nenhum preço foi enviado explicitamente, usa o preço real do Place —
                // nunca confia em um preço de cliente sem contrapartida no cadastro.
                'price' => $request['price'] ?? optional(Place::find($request['place_id']))->price,
                'created_by_user' => $request['created_by_user'] ?? null,
            ]));
        }
        
        
        $member = Member::find($request['member_id']);
        $place = Place::find($request['place_id']);
        $dateFormated = Carbon::createFromFormat('Y-m-d', $request->input('date'))->locale('pt_BR')->isoFormat('DD [de] MMMM [de] YYYY');
        
        $timesSlotsCount = count($request->input('selected_slots'));
        $timesSlotsString = implode(" / ", $request->input('selected_slots'));
        $mailMsg = [
            'place_name' => $place->group->name . ' - ' . $place->name,
            'name' => $member->name,
            'email' => $member->email,
            'time' => $timesSlotsString,
            'date' => $dateFormated,
            'email_type' => $request->input('status_id') == 3 ? 'schedule.pending' : 'schedule.confirm',
            'status_id' => $request->input('status_id') ?? 1,
            'price' => number_format(($request['price'] ?? 0) * $timesSlotsCount, 2, ',', '.'),
        ];
        
        $this->sendScheduleEmail($mailMsg);


        return $schedules;
    }


    private function isValidScheduleTime($place_id, $time_start, $time_end, $date){
        $options = $this->scheduleRulesService->getTimeOptions($place_id, $date);
        // dd($options);
        foreach ($options as $option) {

            if ($option['start_time'] == $time_start && $option['end_time'] == $time_end) {
                return true;
            }
        }
        return false;
    }

    public function store(Request $request)
    {
        $validated = $request->all();

        try {
            $schedule = Schedule::create($validated);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Backstop de banco (índice único em active_slot_key): outra requisição
            // concorrente reservou esse horário entre a checagem de colisão e o insert.
            throw new \Exception("Horário colide com outro agendamento.");
        }

        return response()->json(['schedule' => $schedule], 201);
    }

    private function sendScheduleEmail($data){

        // dd($data);
        $emailData = [

            'email' => $data['email'],
            // 'email' => 'al.gustavo@outlook.com',
            'type' => $data['email_type'], // 'schedule.confirm' ou 'schedule.pending'
            'subject' => "Agendamento " . ($data['email_type'] == 'schedule.confirm' ? 'Confirmado' : 'Pendente'),
            'name' => $data['name'],
            'place_name' => $data['place_name'],
            'time' => $data['time'],
            'date' => $data['date'],
            'price' => $data['price']
            
        ];

        $this->emailService->processContactForm($emailData);
    }


    public function checkColide($slotStartTime, $slotEndTime, $place_id, $date, $member_id = null){
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
                return [$member, $schedule->status_id, "Horário reservado por outro associado.", $schedule]; // Collision detected
            }
        }
        if ($member_id) {
            // Check for member-specific schedules
            $memberSchedules = Schedule::where('member_id', $member_id)
                ->whereIn('status_id', [1, 3]) 
                ->whereDate('start_schedule', $date)
                ->get();
            
            foreach ($memberSchedules as $schedule) {
                $scheduleStart = strtotime(date('H:i', strtotime($schedule->start_schedule)));
                $scheduleEnd = strtotime(date('H:i', strtotime($schedule->end_schedule)));

                // Check for overlap
                if (!($slotEnd <= $scheduleStart || $slotStart >= $scheduleEnd)) {
                    $member = Member::find($schedule->member_id);
                    return [$member, $schedule->status_id, "Você já possui um agendamento nesse horário.", $schedule ]; // Collision detected
                }
            }
        }


        return [null, null, null, null]; // No collision
    }

    public function countMemberSchedulesInPlaceGroupOnDate($group, $member_id, $date){
        $placesIds = $group->places->pluck('id')->toArray();

        $count = Schedule::whereIn('place_id', $placesIds)
            ->where('member_id', $member_id)
            ->whereIn('status_id', [1, 3]) // Exclude cancelled and pending schedules
            ->whereDate('start_schedule', $date)
            ->count();

        $remaining = $group->daily_limit - $count;

        $response = [
            'limit' => $group->daily_limit,
            'remaining' => $remaining 
        ];

        return $response;
    }

    public function homeAssistantAutomation(){

        $now = Carbon::now();


        //Get schedules than start in 5 minutes or started in 5 minutes
        $schedules = Schedule::where('status_id', 1)
        ->whereDate('start_schedule', Carbon::now()->toDateString())->get();

        
        $places_schedules = [];

        foreach ($schedules as $schedule) {
            // Lights ON = start_schedule - 5 minutes
            // Lights OFF = end_schedule + 5 minutes

            $schedule->lights_on = $schedule->start_schedule->copy()->subMinutes(5);
            $schedule->lights_off = $schedule->end_schedule->copy()->addMinutes(5);
            // dd($schedule->lights_on, $schedule->lights_off, $now);
            if ($now->between($schedule->lights_on, $schedule->lights_off)) {
                $places_schedules[] = $schedule->place->id;
            }
        }

        array_unique($places_schedules);

        $places = Place::query()
            // ->whereIn('id', array_unique($places))
            ->whereNotNull('contactor')
            ->where('contactor', '!=', '')
            ->get();

        $contactors = [];

        // dd($places, $places_schedules);

        foreach ($places as $place) {
            $contactors[$place->contactor] = in_array($place->id, $places_schedules);
        }

        return response()->json(['contactors' => $contactors], 200);
    }


        




}

