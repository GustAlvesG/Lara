<?php

namespace App\Http\Controllers;

use App\Models\Parking;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreParkingRequest;
use App\Http\Requests\UpdateParkingRequest;
use Illuminate\Http\Request;
use App\Http\Controllers\FtpController;
use App\Http\Controllers\AccessController;
use App\Services\ParkingAccessCorrelationService;
use App\Services\Parking\LikelyDrivers;
use DateTime;

class ParkingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function search()
    {
        $startOfDay = date('Y-m-d\T00:00:00');
        $endOfDay = date('Y-m-d\T23:59:59');
        $todayParking = Parking::whereBetween('entry_date', [$startOfDay, $endOfDay])->get();

        $todayParkingCount = $todayParking->count();
        $todayParkingNoPlate = $todayParking->where('plate', 'Sem placa')->count();
        return view('parking.search')->with('todayParkingCount', $todayParkingCount)->with('todayParkingNoPlate', $todayParkingNoPlate);
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
    public function store(StoreParkingRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    // Método 'show' do controlador 'ParkingController'
    public function show(Request $request)
    {
        // Obtém todos os dados da requisição
        $inputs = $request->all();
        // Extrai a placa do veículo e a data dos dados da requisição
        $plate = $inputs['plate'];
        $datetime = $inputs['datetime'];

        // Define o início e o fim do dia com base na data fornecida
        $startOfDay = date('Y-m-d\T00:00:00', strtotime($datetime));
        $endOfDay = date('Y-m-d\T23:59:59', strtotime($datetime));

        // Se a data não for fornecida, define o início e o fim do dia como 'hoje'
        if ($datetime == null) {
            $startOfDay = date('Y-m-d\T00:00:00', strtotime('today'));
            $endOfDay = date('Y-m-d\T23:59:59', strtotime('today'));
        }

        // Cria um array com os dados do carro
        $car = [
            'plate' => $plate,
            'datetime' => $datetime
        ];

        // Busca os dados de estacionamento com base na placa do veículo e na data fornecida
        $data = Parking::where('plate', $plate)->whereBetween('entry_date', [$startOfDay, $endOfDay])->get();

        // Externos (terceirizado, freelancer, liberação pontual) e pedidos de
        // carro de aplicativo: o pedido casa pela placa, o externo pelo horário.
        $correlation = app(ParkingAccessCorrelationService::class);
        $appCarRequests = $correlation->appCarRequests((string) $plate, $startOfDay, $endOfDay);
        $appCarsByEntry = $correlation->appCarsByEntry($data->pluck('entry_date', 'id')->all(), $appCarRequests);

        // Inicializa arrays para os dados da resposta
        $response = [];

        // Itera sobre os dados de estacionamento
        foreach ($data as $item) {
            // Formata a data de entrada
            $temp_date = explode(' ', $item->entry_date);
            $temp_date[1] = str_replace('-', ':', $temp_date[1]);
            $date = $temp_date[0] . " " . $temp_date[1];
            $date = new Datetime($date);
            $date = $date->format('H:i:s d/m/Y');

            // Adiciona os dados formatados à resposta
            $response[] = [
                'entry_date' => $date,
                // Chama o método 'getImage' do controlador 'FtpController' para obter a imagem do carro
                'file' => FtpController::getImage($item->file),
                // Chama o método 'findAccessByTime' do controlador 'AccessController' para obter os dados de acesso
                'access' => AccessController::findAccessByTime($item->entry_date, $item->gate),
                // Externos registrados na portaria em torno do mesmo horário
                'externals' => $correlation->externalsAround($item->entry_date),
                // Pedidos de carro de aplicativo desta placa liberados nesta leitura
                'app_cars' => $appCarsByEntry[$item->id] ?? [],
            ];

            // Adiciona a cor do carro ao array do carro
            $car['color'] = $item->color;
        };

        $lasts = Parking::where('plate', $plate)->where('entry_date', '<', $endOfDay)->orderBy('entry_date', 'desc')->limit(10)->get();

        // Condutores prováveis: as pessoas ligadas a cada uma das 10 últimas
        // leituras. Externo liberado também é candidato (o tipo vira selo).
        // Carro de aplicativo fica de fora: quem pediu é o passageiro, não
        // quem dirige. A conta mora em LikelyDrivers.
        $readings = [];
        foreach ($lasts as $item) {
            $candidates = [];
            foreach (AccessController::findAccessByTime($item->entry_date, $item->gate) as $access) {
                $candidates[] = ['name' => $access->Name, 'telephone' => $access->Telephone];
            }
            foreach ($correlation->externalsAround($item->entry_date) as $external) {
                if ($external['allowed']) {
                    $candidates[] = ['name' => $external['name'], 'telephone' => $external['telephone'], 'label' => $external['label']];
                }
            }
            $readings[] = $candidates;
        }

        $likelyDrivers = LikelyDrivers::rank($readings);

        // Retorna a view 'parking.show' com os dados da resposta e do carro
        return view('parking.show')->with('data', array_reverse($response))->with('plate', $plate)->with('datetime', $datetime)->with('car', $car)->with('likelyDrivers', $likelyDrivers)->with('appCarRequests', $appCarRequests);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Parking $parking)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateParkingRequest $request, Parking $parking)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Parking $parking)
    {
        //
    }
}
