<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\Parking;
use App\Models\ParkingAuthorization;
use App\Models\Schedule;
use App\Models\SchedulePayment;
use App\Models\Status;
use App\Models\DataInfo;
use App\Models\Company\Company;
use App\Models\Company\CompanyWorker;
use App\Models\Company\CompanyAccessLog;
use App\Models\Contactor;

class DashboardController extends Controller
{
    /**
     * Painel inicial. Cada bloco de métricas só é calculado quando o
     * usuário tem a permissão correspondente, evitando consultas desnecessárias.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $data = [
            'user' => $user,
        ];

        // Últimos 14 dias (labels d/m e chaves Y-m-d para casar com as queries)
        $days = collect(range(13, 0))->map(fn ($i) => Carbon::today()->subDays($i));
        $dayKeys = $days->map(fn ($d) => $d->format('Y-m-d'));
        $dayLabels = $days->map(fn ($d) => $d->format('d/m'));

        // SIV / Estacionamento ------------------------------------------------
        if ($user->can('search parking')) {
            $parkingByDay = Parking::selectRaw('DATE(entry_date) as day, COUNT(*) as total')
                ->where('entry_date', '>=', Carbon::today()->subDays(13)->startOfDay())
                ->groupBy('day')
                ->pluck('total', 'day');

            $data['parking'] = [
                'today' => Parking::whereDate('entry_date', Carbon::today())->count(),
                'month' => Parking::whereBetween('entry_date', [
                    Carbon::now()->startOfMonth(),
                    Carbon::now()->endOfMonth(),
                ])->count(),
                'authTotal' => ParkingAuthorization::count(),
                'authExpiring' => ParkingAuthorization::whereBetween('expiration_date', [
                    Carbon::today(),
                    Carbon::today()->addDays(30),
                ])->count(),
                'chart' => [
                    'labels' => $dayLabels,
                    'data' => $dayKeys->map(fn ($k) => (int) ($parkingByDay[$k] ?? 0)),
                ],
            ];
        }

        // Reservas ------------------------------------------------------------
        if ($user->can('view reservations')) {
            $statusNames = Status::pluck('portuguese', 'id');

            $byStatus = Schedule::whereDate('start_schedule', Carbon::today())
                ->selectRaw('status_id, COUNT(*) as total')
                ->groupBy('status_id')
                ->pluck('total', 'status_id');

            $data['reservations'] = [
                'today' => Schedule::whereDate('start_schedule', Carbon::today())->count(),
                'upcomingCount' => Schedule::where('start_schedule', '>=', Carbon::now())->count(),
                'revenue' => (float) SchedulePayment::whereMonth('paid_at', Carbon::now()->month)
                    ->whereYear('paid_at', Carbon::now()->year)
                    ->sum('paid_amount'),
                'upcoming' => Schedule::with(['place.group', 'member', 'status'])
                    ->where('start_schedule', '>=', Carbon::now())
                    ->orderBy('start_schedule')
                    ->limit(6)
                    ->get(),
                'chart' => [
                    'labels' => $byStatus->keys()->map(fn ($id) => $statusNames[$id] ?? "Status {$id}")->values(),
                    'data' => $byStatus->values(),
                ],
            ];
        }

        // Parceiros (empresas terceirizadas) — visível a todos os autenticados
        $logsByDay = CompanyAccessLog::selectRaw('DATE(created_at) as day, allowed, COUNT(*) as total')
            ->where('created_at', '>=', Carbon::today()->subDays(13)->startOfDay())
            ->groupBy('day', 'allowed')
            ->get();

        $allowedByDay = $logsByDay->where('allowed', true)->pluck('total', 'day');
        $deniedByDay = $logsByDay->where('allowed', false)->pluck('total', 'day');

        $data['partners'] = [
            'companies' => Company::count(),
            'workers' => CompanyWorker::count(),
            'allowedToday' => CompanyAccessLog::whereDate('created_at', Carbon::today())->where('allowed', true)->count(),
            'deniedToday' => CompanyAccessLog::whereDate('created_at', Carbon::today())->where('allowed', false)->count(),
            'chart' => [
                'labels' => $dayLabels,
                'allowed' => $dayKeys->map(fn ($k) => (int) ($allowedByDay[$k] ?? 0)),
                'denied' => $dayKeys->map(fn ($k) => (int) ($deniedByDay[$k] ?? 0)),
            ],
        ];

        // InfoClube -----------------------------------------------------------
        if ($user->can('view information')) {
            // Cada informação tem várias versões em data_infos (mesma information_id).
            // Só a última versão de cada informação (com Information não deletada) conta.
            $latestInfos = DataInfo::whereHas('information', fn ($q) => $q->whereNull('deleted_at'))
                ->get()
                ->groupBy('information_id')
                ->map->sortByDesc('created_at')
                ->map->first();

            $data['info'] = [
                'total' => $latestInfos->count(),
                'categories' => $latestInfos->filter(fn ($i) => filled($i->category))
                    ->groupBy('category')
                    ->map->count()
                    ->sortDesc()
                    ->take(5),
            ];
        }

        // Home Assistant (interruptores / contatores) -------------------------
        if ($user->can('manage home assistant')) {
            $data['homeAssistant'] = [
                'contactors' => Contactor::with([
                    'places',
                    'overrides' => fn ($q) => $q->with(['weekdays', 'windows']),
                ])->orderBy('name')->get(),
            ];
        }

        return view('dashboard', $data);
    }
}
