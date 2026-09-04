<?php

namespace App\Http\Requests\Fleet;

/**
 * Mesmas regras do cadastro: as duas checagens de unicidade (nome e placa) já
 * ignoram o próprio veículo quando ele está na rota.
 */
class UpdateFleetVehicleRequest extends StoreFleetVehicleRequest
{
}
