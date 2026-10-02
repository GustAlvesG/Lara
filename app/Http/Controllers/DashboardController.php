<?php

namespace App\Http\Controllers;

use App\Models\Aviso;
use App\Models\Company\Company;
use App\Models\Company\CompanyAccessLog;
use App\Models\Company\CompanyWorker;
use App\Models\DataInfo;
use App\Models\Parking;
use App\Models\ParkingAuthorization;
use App\Models\Schedule;
use App\Models\SchedulePayment;
use App\Services\HomeAssistant\ContactorStateResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Painel inicial — a primeira tela de todo mundo, então precisa abrir rápido.
 *
 * O que mantém isso leve:
 *   - só números do dia, sem gráficos (eram três consultas agrupadas de 14
 *     dias e o Chart.js baixado de CDN a cada abertura);
 *   - os números são do clube, não da pessoa: ficam em cache por um minuto e
 *     servem a todos (`numbers()`);
 *   - filtro de data por intervalo, e não `whereDate`/`whereMonth`, para o
 *     banco conseguir usar o índice da coluna;
 *   - contagem feita no banco. O InfoClube carregava todas as versões de
 *     todas as informações, com o texto, só para contar.
 *
 * Cada bloco só é calculado para quem tem a permissão correspondente.
 */
class DashboardController extends Controller
{
    /** Uma linha do grid (3 colunas no lg); o resto fica em "Ver todos". */
    private const AVISOS_DASHBOARD_LIMIT = 3;

    private const UPCOMING_LIMIT = 5;

    /** Segundos que os números do clube ficam em cache. */
    private const NUMBERS_TTL = 60;

    public function index(Request $request)
    {
        $user = $request->user();

        $data = ['user' => $user];

        // Avisos que a pessoa ainda não abriu (conforme a regra de visibilidade).
        $data['avisos'] = Aviso::with('creator', 'lembretes')
            ->visibleTo($user)
            ->active()
            ->notViewedBy($user)
            ->orderByDesc('created_at')
            ->limit(self::AVISOS_DASHBOARD_LIMIT)
            ->get();

        $today = [Carbon::today(), Carbon::today()->endOfDay()];

        // SIV / Estacionamento ------------------------------------------------
        if ($user->can('siv.busca')) {
            $data['parking'] = $this->numbers('siv', fn () => [
                'today' => Parking::whereBetween('entry_date', $today)->count(),
                'authTotal' => ParkingAuthorization::count(),
                'authExpiring' => ParkingAuthorization::whereBetween('expiration_date', [
                    Carbon::today(),
                    Carbon::today()->addDays(30),
                ])->count(),
            ]);
        }

        // Reservas ------------------------------------------------------------
        if ($user->can('reservas.agendamentos')) {
            $data['reservations'] = $this->numbers('reservas', fn () => [
                'today' => Schedule::whereBetween('start_schedule', $today)->count(),
                'upcomingCount' => Schedule::where('start_schedule', '>=', Carbon::now())->count(),
                'revenue' => (float) SchedulePayment::whereBetween('paid_at', [
                    Carbon::now()->startOfMonth(),
                    Carbon::now()->endOfMonth(),
                ])->sum('paid_amount'),
            ]);

            // Fora do cache: são models, e a lista muda a cada reserva feita.
            $data['reservations']['upcoming'] = Schedule::with(['place.group', 'member', 'status'])
                ->where('start_schedule', '>=', Carbon::now())
                ->orderBy('start_schedule')
                ->limit(self::UPCOMING_LIMIT)
                ->get();
        }

        // Externos (empresas terceirizadas) — visível a todos os autenticados
        $data['partners'] = $this->numbers('externos', function () use ($today) {
            // Permitidos e negados de hoje numa consulta só.
            $byResult = CompanyAccessLog::whereBetween('created_at', $today)
                ->selectRaw('allowed, COUNT(*) as total')
                ->groupBy('allowed')
                ->pluck('total', 'allowed');

            return [
                'companies' => Company::count(),
                'workers' => CompanyWorker::count(),
                'allowedToday' => (int) ($byResult[1] ?? 0),
                'deniedToday' => (int) ($byResult[0] ?? 0),
            ];
        });

        // InfoClube (de todo mundo logado) -----------------------------------
        // Cada informação tem várias versões em data_infos (mesma
        // information_id); conta uma por informação não removida.
        $data['info'] = $this->numbers('info', fn () => [
            'total' => DataInfo::whereHas('information', fn ($q) => $q->whereNull('deleted_at'))
                ->distinct()
                ->count('information_id'),
        ]);

        // Home Assistant (interruptores / contatores) -------------------------
        // Sem cache: é um controle, tem de mostrar o estado de agora.
        if ($user->can('home-assistant')) {
            $resolver = new ContactorStateResolver();
            $contactors = $resolver->contactors();

            $data['homeAssistant'] = [
                'contactors' => $contactors,
                'states'     => $resolver->resolveAll($contactors),
            ];
        }

        return view('dashboard', $data);
    }

    /**
     * Números de um bloco, em cache por um minuto. São contagens do clube —
     * iguais para qualquer pessoa —, então um cálculo serve a todos que
     * abrirem o painel nesse intervalo.
     */
    private function numbers(string $block, Closure $compute): array
    {
        return Cache::remember("dashboard:{$block}", self::NUMBERS_TTL, $compute);
    }
}
