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
use App\Services\SchedulesService;


class ScheduleController extends Controller
{
    protected $jwtService;

    public function __construct(\App\Providers\Services\JwtService $jwtService)
    {
        $this->schedulesService = new SchedulesService();
        $this->jwtService = $jwtService;
    }


    public function index(Request $request){
        try {
            $date = $request->input('date', Carbon::today()->toDateString());
    
            $modalities = $this->schedulesService->getSchedules($date);

        
            // return $modalities->toArray();
            return view('location.index', compact('modalities', 'date'));
        }
        catch (\Exception $e) {
            return redirect()->back()->with('error', 'Ocorreu um erro ao carregar os agendamentos: ' . $e->getMessage());
        }
    }

    public function update(Request $request)
    {
        try {
            $updates = $request->all();

            $validator = Validator::make($updates, [
                'selected_reservations' => 'required|array',
                'action_status' => 'required',
                // Motivo obrigatório apenas quando a ação é cancelamento (status_id 0).
                'cancel_reason' => 'required_if:action_status,0|nullable|string|max:1000',
            ], [
                'cancel_reason.required_if' => 'É necessário informar o motivo do cancelamento.',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }

            $response = $this->schedulesService->updateSchedulesStatus($updates);
            $id = $request->id ?? $updates['selected_reservations'][0] ?? null;
            return redirect()->route($id ? 'schedule.show' : 'schedule.index', $id ? ['id' => $id] : [])->with('success', 'Agendamentos atualizado com sucesso!');
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while updating schedules status.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $route_name = $request->route()->getName();
        $isApi = 'api' == explode('.', $route_name)[0];

        if ($isApi) {
            $sessionToken = $request->header('Session');

            if ($sessionToken) {
                // Chamada de um membro (via Clubeel/Next.js): a identidade, o preço
                // e o status nunca vêm do body — são sempre derivados da sessão e do
                // preço real cadastrado no Place, nunca do que o cliente envia.
                try {
                    $payload = $this->jwtService->validateToken($sessionToken);
                } catch (\Exception $e) {
                    return response()->json(['message' => 'Sessão inválida.'], 401);
                }

                $member = Member::where('cpf', $payload['username'] ?? null)->first();
                if (!$member) {
                    return response()->json(['message' => 'Sessão inválida.'], 401);
                }

                $payloadData = $request->all();
                $payloadData['member_id'] = $member->id;
                $payloadData['status_id'] = 3; // Sempre pendente de pagamento nesse fluxo.
                unset($payloadData['cpf'], $payloadData['price']);
                $request = new Request($payloadData);
            } elseif (!Auth()->check()) {
                // Nem sessão de membro (header Session) nem sessão administrativa
                // (painel interno autenticado): sem nenhuma das duas, não cria agendamento.
                return response()->json(['message' => 'Não autorizado.'], 401);
            }
        }

        try {
            $scheudles = $this->schedulesService->createSchedule(new Request($request->all()));
            if ('api' == explode('.', $route_name)[0]) {
                $response = response()->json([
                    'message' => 'Schedule created successfully',
                    'schedules' => $scheudles,
                    'count' => count($scheudles),
                ], 201);
            } else {

            $response = redirect()->back()->with('success', 'Agendamento criado com sucesso!');
            }
            return $response;
        } catch (\Exception $e) {
            if ('api' == explode('.', $route_name)[0]) {
                return response()->json([
                    'message' => 'An error occurred while creating schedule.',
                    'error' => $e->getMessage(),
                ], 500);
            }
            else {
                return redirect()->back()->with('error', 'Erro ao criar agendamento: ' . $e->getMessage());

            }
        }
    }

    public function indexByMember(Request $request, $member_id)
    {
        try {
            $sessionUser = $request->input('user');
            $requester = $sessionUser['username'] ?? null;

            $owner = Member::where('id', $member_id)->value('cpf');

            if (!$requester || !$owner || $owner !== $requester) {
                return response()->json(['message' => 'Acesso não autorizado a este recurso.'], 403);
            }

            $response = $this->schedulesService->getScheduleByMember($member_id);
            return $response;
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching schedules.',
                'error' => $e->getMessage(),
            ], 500);
        }
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

    public function show($id)
    {
        $schedule = Schedule::with(['status','place.group','member', 'creator', 'editor'])->find($id);

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

        $data = [
            'schedule' => $schedule,
            'other_schedules' => $other_schedules
        ];


     
        return view('location.schedule.show')->with('data', $data);
    }

    public function homeAssistantAutomation(){
        try {
            $response = $this->schedulesService->homeAssistantAutomation();
            return $response;
        }catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
        
    }
}
